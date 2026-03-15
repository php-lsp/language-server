<?php

declare(strict_types=1);

namespace App\Tests\Playground\Support;

/**
 * LSP client that communicates with the server over TCP using JSON-RPC 2.0
 * with Content-Length framing (LSP transport protocol).
 */
final class LspClient
{
    /** @var resource|null */
    private $socket = null;

    private int $nextId = 1;

    /** @var array<int|string, array{method: string, response: ?array<string, mixed>}> */
    private array $pendingRequests = [];

    /** @var list<array<string, mixed>> */
    private array $notifications = [];

    /**
     * Connect to the LSP server via TCP.
     *
     * @param string $host Server host
     * @param int $port Server port
     * @param float $timeout Connection timeout in seconds
     * @throws \RuntimeException If connection fails
     */
    public function connect(string $host, int $port, float $timeout = 5.0): void
    {
        $socket = @\fsockopen($host, $port, $errno, $errstr, $timeout);

        if ($socket === false) {
            throw new \RuntimeException(\sprintf(
                'Failed to connect to LSP server at %s:%d: [%d] %s',
                $host,
                $port,
                $errno,
                $errstr,
            ));
        }

        \stream_set_timeout($socket, (int) $timeout, (int) (($timeout - (int) $timeout) * 1_000_000));
        $this->socket = $socket;
    }

    /**
     * Disconnect from the server.
     */
    public function disconnect(): void
    {
        if ($this->socket !== null) {
            @\fclose($this->socket);
            $this->socket = null;
        }
    }

    /**
     * Send an LSP request and return the response.
     *
     * @param string $method The LSP method name
     * @param array<string, mixed> $params Request parameters
     * @param float $timeout Response timeout in seconds
     * @return array<string, mixed> The JSON-RPC response
     * @throws \RuntimeException On communication errors
     */
    public function request(string $method, array $params = [], float $timeout = 30.0): array
    {
        $id = $this->nextId++;

        $message = [
            'jsonrpc' => '2.0',
            'id' => $id,
            'method' => $method,
            'params' => (object) $params,
        ];

        $this->sendMessage($message);

        return $this->waitForResponse($id, $timeout);
    }

    /**
     * Send an LSP notification (no response expected).
     *
     * @param string $method The LSP method name
     * @param array<string, mixed> $params Notification parameters
     */
    public function notify(string $method, array $params = []): void
    {
        $message = [
            'jsonrpc' => '2.0',
            'method' => $method,
            'params' => (object) $params,
        ];

        $this->sendMessage($message);
    }

    /**
     * Read all pending server notifications.
     *
     * @param float $timeout How long to wait for notifications
     * @return list<array<string, mixed>>
     */
    public function drainNotifications(float $timeout = 0.5): array
    {
        $this->readAvailableMessages($timeout);
        $notifications = $this->notifications;
        $this->notifications = [];
        return $notifications;
    }

    /**
     * Get all collected notifications without draining.
     *
     * @return list<array<string, mixed>>
     */
    public function getNotifications(): array
    {
        return $this->notifications;
    }

    /**
     * Perform the LSP initialize handshake.
     *
     * @param string $rootUri The workspace root URI
     * @param array<string, mixed> $capabilities Client capabilities
     * @return array<string, mixed> The InitializeResult
     */
    public function initialize(string $rootUri, array $capabilities = []): array
    {
        $response = $this->request('initialize', [
            'processId' => \getmypid(),
            'rootUri' => $rootUri,
            'capabilities' => (object) $capabilities,
            'workspaceFolders' => [
                [
                    'uri' => $rootUri,
                    'name' => \basename(\parse_url($rootUri, \PHP_URL_PATH) ?: ''),
                ],
            ],
        ]);

        // Send initialized notification
        $this->notify('initialized', []);

        return $response;
    }

    /**
     * Open a text document in the editor.
     *
     * @param string $uri The document URI
     * @param string $text The document content
     * @param string $languageId The language identifier
     */
    public function openDocument(string $uri, string $text, string $languageId = 'php'): void
    {
        $this->notify('textDocument/didOpen', [
            'textDocument' => [
                'uri' => $uri,
                'languageId' => $languageId,
                'version' => 1,
                'text' => $text,
            ],
        ]);
    }

    /**
     * Send a shutdown request followed by exit notification.
     */
    public function shutdown(): void
    {
        try {
            $this->request('shutdown', [], 5.0);
            $this->notify('exit');
        } catch (\Throwable) {
            // Ignore errors during shutdown
        }
    }

    /**
     * @param array<string, mixed> $message
     */
    private function sendMessage(array $message): void
    {
        if ($this->socket === null) {
            throw new \RuntimeException('Not connected to LSP server');
        }

        $json = \json_encode($message, \JSON_UNESCAPED_UNICODE | \JSON_THROW_ON_ERROR);
        $length = \strlen($json);

        $frame = "Content-Length: {$length}\r\n\r\n{$json}";
        $written = @\fwrite($this->socket, $frame);

        if ($written === false || $written === 0) {
            throw new \RuntimeException('Failed to write to LSP server');
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function waitForResponse(int $id, float $timeout): array
    {
        $deadline = \microtime(true) + $timeout;

        while (\microtime(true) < $deadline) {
            $message = $this->readMessage($deadline - \microtime(true));

            if ($message === null) {
                continue;
            }

            // If this is a response to our request
            if (isset($message['id']) && (int) $message['id'] === $id) {
                return $message;
            }

            // If this is a notification from the server
            if (!isset($message['id']) && isset($message['method'])) {
                $this->notifications[] = $message;
                continue;
            }

            // If this is a response to a different request, store it
            if (isset($message['id'])) {
                $this->pendingRequests[(int) $message['id']]['response'] = $message;
            }
        }

        throw new \RuntimeException(\sprintf(
            'Timeout waiting for response to request id=%d (%.1fs)',
            $id,
            $timeout,
        ));
    }

    private function readAvailableMessages(float $timeout): void
    {
        $deadline = \microtime(true) + $timeout;

        while (\microtime(true) < $deadline) {
            $message = $this->readMessage($deadline - \microtime(true));

            if ($message === null) {
                break;
            }

            if (!isset($message['id']) && isset($message['method'])) {
                $this->notifications[] = $message;
            }
        }
    }

    /**
     * @return array<string, mixed>|null
     */
    private function readMessage(float $timeout): ?array
    {
        if ($this->socket === null) {
            throw new \RuntimeException('Not connected to LSP server');
        }

        $timeoutSec = \max(0, (int) $timeout);
        $timeoutUsec = \max(0, (int) (($timeout - $timeoutSec) * 1_000_000));
        \stream_set_timeout($this->socket, $timeoutSec, $timeoutUsec);

        // Read headers until double CRLF
        $headers = '';
        while (true) {
            $byte = @\fread($this->socket, 1);

            if ($byte === false || $byte === '') {
                $info = \stream_get_meta_data($this->socket);
                if ($info['timed_out']) {
                    return null;
                }
                if ($info['eof']) {
                    throw new \RuntimeException('LSP server disconnected');
                }
                return null;
            }

            $headers .= $byte;

            if (\str_ends_with($headers, "\r\n\r\n")) {
                break;
            }
        }

        // Parse Content-Length
        if (!\preg_match('/Content-Length:\s*(\d+)/i', $headers, $matches)) {
            throw new \RuntimeException('Missing Content-Length header in LSP message');
        }

        $contentLength = (int) $matches[1];

        // Read body
        $body = '';
        $remaining = $contentLength;

        // Reset timeout for body reading
        \stream_set_timeout($this->socket, 10);

        while ($remaining > 0) {
            $chunk = @\fread($this->socket, $remaining);

            if ($chunk === false || $chunk === '') {
                throw new \RuntimeException('Connection lost while reading message body');
            }

            $body .= $chunk;
            $remaining -= \strlen($chunk);
        }

        return \json_decode($body, true, 512, \JSON_THROW_ON_ERROR);
    }
}

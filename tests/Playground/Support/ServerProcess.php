<?php

declare(strict_types=1);

namespace App\Tests\Playground\Support;

use Symfony\Component\Process\Process;

/**
 * Manages the LSP server process lifecycle for E2E tests.
 *
 * Starts the server as a background process, waits for it to become ready,
 * and handles cleanup on shutdown. Uses Symfony Process for cross-platform
 * compatibility (Linux, macOS, Windows).
 */
final class ServerProcess
{
    private const string SERVER_BINARY = 'bin/lsp';
    private const string KERNEL_CLASS = 'App\\Application';
    private const float STARTUP_TIMEOUT = 15.0;
    private const float RETRY_INTERVAL = 0.1;

    private ?Process $process = null;

    private string $host = '127.0.0.1';
    private int $port;

    /**
     * @param string $projectRoot The root directory of the LSP project
     * @param string|null $coverageDir Directory for coverage data (null = no coverage)
     */
    public function __construct(
        private readonly string $projectRoot,
        private readonly ?string $coverageDir = null,
    ) {
        $this->port = self::findFreePort();
    }

    /**
     * Start the LSP server process.
     *
     * @throws \RuntimeException If the server fails to start
     */
    public function start(): void
    {
        $command = $this->buildCommand();
        $env = $this->buildEnvironment();

        $this->process = new Process(
            command: $command,
            cwd: $this->projectRoot,
            env: $env,
            timeout: null,
        );

        $this->process->start();

        $this->waitForReady();
    }

    /**
     * Stop the LSP server process.
     */
    public function stop(): void
    {
        if ($this->process === null) {
            return;
        }

        $this->process->stop(3.0);
        $this->process = null;
    }

    public function getHost(): string
    {
        return $this->host;
    }

    public function getPort(): int
    {
        return $this->port;
    }

    /**
     * Get the server's stdout output (for debugging).
     */
    public function getStdout(): string
    {
        return $this->process?->getOutput() ?? '';
    }

    /**
     * Get the server's stderr output (for debugging).
     */
    public function getStderr(): string
    {
        return $this->process?->getErrorOutput() ?? '';
    }

    /**
     * Check if the process is still running.
     */
    public function isRunning(): bool
    {
        return $this->process?->isRunning() ?? false;
    }

    /**
     * Build the server command.
     *
     * @return list<string>
     */
    private function buildCommand(): array
    {
        $php = \PHP_BINARY;
        $binary = $this->projectRoot . '/' . self::SERVER_BINARY;

        $cmd = [$php];

        // Add coverage-related PHP options
        if ($this->coverageDir !== null) {
            if (\extension_loaded('pcov')) {
                $cmd[] = '-dpcov.enabled=1';
                $cmd[] = '-dpcov.directory=' . $this->projectRoot . '/app';
            } elseif (\extension_loaded('xdebug')) {
                $cmd[] = '-dxdebug.mode=coverage';
            }
        }

        $cmd[] = $binary;
        $cmd[] = 'serve';
        $cmd[] = self::KERNEL_CLASS;
        $cmd[] = '--port=' . $this->port;
        $cmd[] = '--addr=' . $this->host;
        $cmd[] = '--env=dev';

        return $cmd;
    }

    /**
     * Build the environment variables for the server process.
     *
     * @return array<string, string>
     */
    private function buildEnvironment(): array
    {
        $env = [];

        // Inherit existing environment
        foreach (\getenv() as $key => $value) {
            if (\is_string($key) && \is_string($value)) {
                $env[$key] = $value;
            }
        }

        $env['APP_ENV'] = 'dev';

        if ($this->coverageDir !== null) {
            $env['E2E_COVERAGE_FILE'] = $this->coverageDir . '/e2e-server-' . \getmypid() . '-' . \time() . '.cov';
        }

        return $env;
    }

    /**
     * Wait for the server to be ready to accept connections.
     *
     * @throws \RuntimeException If the server doesn't become ready in time
     */
    private function waitForReady(): void
    {
        $deadline = \microtime(true) + self::STARTUP_TIMEOUT;

        while (\microtime(true) < $deadline) {
            // Check if the process crashed
            if (!$this->isRunning()) {
                throw new \RuntimeException(\sprintf(
                    "LSP server process exited unexpectedly.\nStdout: %s\nStderr: %s",
                    $this->getStdout(),
                    $this->getStderr(),
                ));
            }

            // Try to connect
            $socket = @\fsockopen($this->host, $this->port, $errno, $errstr, 0.5);
            if ($socket !== false) {
                @\fclose($socket);
                return;
            }

            \usleep((int) (self::RETRY_INTERVAL * 1_000_000));
        }

        throw new \RuntimeException(\sprintf(
            "LSP server did not become ready within %.0f seconds on %s:%d.\nStderr: %s",
            self::STARTUP_TIMEOUT,
            $this->host,
            $this->port,
            $this->getStderr(),
        ));
    }

    /**
     * Find a free TCP port.
     */
    private static function findFreePort(): int
    {
        $socket = \socket_create(\AF_INET, \SOCK_STREAM, \SOL_TCP);
        if ($socket === false) {
            return \random_int(10000, 60000);
        }

        \socket_bind($socket, '127.0.0.1', 0);
        \socket_getsockname($socket, $addr, $port);
        \socket_close($socket);

        return $port;
    }
}

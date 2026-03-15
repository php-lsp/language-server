<?php

declare(strict_types=1);

namespace App\Tests\Playground\Support;

/**
 * Manages the LSP server process lifecycle for E2E tests.
 *
 * Starts the server as a background process, waits for it to become ready,
 * and handles cleanup on shutdown. Supports code coverage collection via
 * XDEBUG or PCOV environment configuration.
 */
final class ServerProcess
{
    private const string SERVER_BINARY = 'bin/lsp';
    private const string KERNEL_CLASS = 'App\\Application';
    private const float STARTUP_TIMEOUT = 15.0;
    private const float RETRY_INTERVAL = 0.1;

    /** @var resource|null */
    private $process = null;

    /** @var array<int, resource> */
    private array $pipes = [];

    private ?int $pid = null;

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
        // Use a random high port to avoid collisions
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

        $descriptors = [
            0 => ['pipe', 'r'],  // stdin
            1 => ['pipe', 'w'],  // stdout
            2 => ['pipe', 'w'],  // stderr
        ];

        $process = \proc_open(
            $command,
            $descriptors,
            $this->pipes,
            $this->projectRoot,
            $env,
        );

        if ($process === false) {
            throw new \RuntimeException('Failed to start LSP server process');
        }

        $this->process = $process;

        $status = \proc_get_status($this->process);
        $this->pid = $status['pid'];

        // Make stdout/stderr non-blocking
        foreach ([1, 2] as $fd) {
            \stream_set_blocking($this->pipes[$fd], false);
        }

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

        // Close stdin to signal the process
        if (isset($this->pipes[0])) {
            @\fclose($this->pipes[0]);
        }

        // Try graceful termination first
        if ($this->pid !== null) {
            @\posix_kill($this->pid, \SIGTERM);
        }

        // Wait briefly for graceful shutdown
        $deadline = \microtime(true) + 3.0;
        while (\microtime(true) < $deadline) {
            $status = \proc_get_status($this->process);
            if (!$status['running']) {
                break;
            }
            \usleep(100_000);
        }

        // Force kill if still running
        $status = \proc_get_status($this->process);
        if ($status['running'] && $this->pid !== null) {
            @\posix_kill($this->pid, \SIGKILL);
        }

        // Close remaining pipes
        foreach ($this->pipes as $pipe) {
            if (\is_resource($pipe)) {
                @\fclose($pipe);
            }
        }

        @\proc_close($this->process);
        $this->process = null;
        $this->pipes = [];
        $this->pid = null;
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
        if (!isset($this->pipes[1])) {
            return '';
        }
        return (string) \stream_get_contents($this->pipes[1]);
    }

    /**
     * Get the server's stderr output (for debugging).
     */
    public function getStderr(): string
    {
        if (!isset($this->pipes[2])) {
            return '';
        }
        return (string) \stream_get_contents($this->pipes[2]);
    }

    /**
     * Check if the process is still running.
     */
    public function isRunning(): bool
    {
        if ($this->process === null) {
            return false;
        }

        $status = \proc_get_status($this->process);
        return $status['running'];
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
            $coverageFile = $this->coverageDir . '/e2e-server-' . \getmypid() . '-' . \time() . '.cov';

            if (\extension_loaded('pcov')) {
                $cmd[] = '-dpcov.enabled=1';
                $cmd[] = '-dpcov.directory=' . $this->projectRoot . '/app';
            } elseif (\extension_loaded('xdebug')) {
                $cmd[] = '-dxdebug.mode=coverage';
            }

            // Pass coverage file path via environment
            \putenv('E2E_COVERAGE_FILE=' . $coverageFile);
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
                $stderr = $this->getStderr();
                $stdout = $this->getStdout();
                throw new \RuntimeException(\sprintf(
                    "LSP server process exited unexpectedly.\nStdout: %s\nStderr: %s",
                    $stdout,
                    $stderr,
                ));
            }

            // Try to connect
            $socket = @\fsockopen($this->host, $this->port, $errno, $errstr, 0.5);
            if ($socket !== false) {
                @\fclose($socket);
                return; // Server is ready
            }

            \usleep((int) (self::RETRY_INTERVAL * 1_000_000));
        }

        $stderr = $this->getStderr();
        throw new \RuntimeException(\sprintf(
            "LSP server did not become ready within %.0f seconds on %s:%d.\nStderr: %s",
            self::STARTUP_TIMEOUT,
            $this->host,
            $this->port,
            $stderr,
        ));
    }

    /**
     * Find a free TCP port.
     */
    private static function findFreePort(): int
    {
        $socket = \socket_create(\AF_INET, \SOCK_STREAM, \SOL_TCP);
        if ($socket === false) {
            // Fallback to random port range
            return \random_int(10000, 60000);
        }

        \socket_bind($socket, '127.0.0.1', 0);
        \socket_getsockname($socket, $addr, $port);
        \socket_close($socket);

        return $port;
    }
}

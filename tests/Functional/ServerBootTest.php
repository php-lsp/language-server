<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Process;

/**
 * Functional tests that verify the LSP server boots correctly.
 *
 * Starts bin/lsp serve as a subprocess and waits to see if the process
 * crashes (non-zero exit code) or stays alive (successful boot).
 */
final class ServerBootTest extends TestCase
{
    private const string BIN_PATH = __DIR__ . '/../../bin/lsp';

    /**
     * How long to wait (seconds) for the server to either crash or prove stable.
     */
    private const float BOOT_TIMEOUT = 3.0;

    #[TestDox('server boots successfully without explicit kernel argument')]
    public function test_server_boots_default(): void
    {
        $this->assertServerBoots([\PHP_BINARY, self::BIN_PATH, 'serve', '--port=0']);
    }

    #[TestDox('server boots successfully with explicit kernel in prod')]
    public function test_server_boots_explicit_kernel_prod(): void
    {
        $this->assertServerBoots([\PHP_BINARY, self::BIN_PATH, 'serve', 'App\\Application', '--env=prod', '--port=0']);
    }

    #[TestDox('server boots successfully with explicit kernel in dev')]
    public function test_server_boots_explicit_kernel_dev(): void
    {
        $this->assertServerBoots([\PHP_BINARY, self::BIN_PATH, 'serve', 'App\\Application', '--env=dev', '--port=0']);
    }

    /**
     * @param list<string> $command
     */
    private function assertServerBoots(array $command): void
    {
        $rootDir = \realpath(__DIR__ . '/../../');
        $command[] = '--root=' . $rootDir;

        $process = new Process(
            command: $command,
            cwd: $rootDir,
            timeout: null,
        );

        $process->start();

        $deadline = \microtime(true) + self::BOOT_TIMEOUT;

        while (\microtime(true) < $deadline) {
            if (!$process->isRunning()) {
                $exitCode = $process->getExitCode();

                $this->assertSame(
                    0,
                    $exitCode,
                    \sprintf(
                        "Server process crashed with exit code %d.\nOutput:\n%s\n%s",
                        $exitCode ?? -1,
                        $process->getOutput(),
                        $process->getErrorOutput(),
                    ),
                );

                return;
            }

            \usleep(100_000);
        }

        // Process still running after timeout — server booted successfully.
        $process->stop(1.0);

        $this->assertTrue(true, 'Server stayed alive for ' . self::BOOT_TIMEOUT . 's — boot successful');
    }
}

<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;

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
        $this->assertServerBoots('php %s serve --port=0');
    }

    #[TestDox('server boots successfully with explicit kernel in prod')]
    public function test_server_boots_explicit_kernel_prod(): void
    {
        $this->assertServerBoots('php %s serve App\\\\Application --env=prod --port=0');
    }

    #[TestDox('server boots successfully with explicit kernel in dev')]
    public function test_server_boots_explicit_kernel_dev(): void
    {
        $this->assertServerBoots('php %s serve App\\\\Application --env=dev --port=0');
    }

    /**
     * @param string $commandTemplate Command template with %s placeholder for bin path
     */
    private function assertServerBoots(string $commandTemplate): void
    {
        $rootDir = realpath(__DIR__ . '/../../');
        $command = sprintf(
            $commandTemplate . ' --root=%s 2>&1',
            escapeshellarg(self::BIN_PATH),
            escapeshellarg($rootDir),
        );

        $descriptorSpec = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        $process = proc_open($command, $descriptorSpec, $pipes);
        $this->assertIsResource($process, 'Failed to start server process');

        fclose($pipes[0]);

        stream_set_blocking($pipes[1], false);
        stream_set_blocking($pipes[2], false);

        $output = '';
        $deadline = microtime(true) + self::BOOT_TIMEOUT;

        while (microtime(true) < $deadline) {
            $stdout = fread($pipes[1], 8192);
            $stderr = fread($pipes[2], 8192);

            if ($stdout !== false) {
                $output .= $stdout;
            }
            if ($stderr !== false) {
                $output .= $stderr;
            }

            $status = proc_get_status($process);
            if (!$status['running']) {
                // Process exited — check if it crashed.
                fclose($pipes[1]);
                fclose($pipes[2]);
                proc_close($process);

                $this->assertSame(
                    0,
                    $status['exitcode'],
                    "Server process crashed with exit code {$status['exitcode']}.\nOutput:\n{$output}",
                );

                return;
            }

            usleep(100_000);
        }

        // Process still running after timeout — server booted successfully.
        fclose($pipes[1]);
        fclose($pipes[2]);
        proc_terminate($process, 15);
        proc_close($process);

        $this->assertTrue(true, 'Server stayed alive for ' . self::BOOT_TIMEOUT . 's — boot successful');
    }
}

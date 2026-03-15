<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;

/**
 * Functional tests that verify the LSP server boots correctly.
 *
 * These tests start the actual server process (bin/lsp serve) and verify
 * that the DI container compiles and the server begins listening.
 */
final class ServerBootTest extends TestCase
{
    private const string BIN_PATH = __DIR__ . '/../../bin/lsp';
    private const string KERNEL_CLASS = 'App\\Application';

    #[TestDox('server boots successfully in prod environment')]
    public function test_server_boots_in_prod(): void
    {
        $this->assertServerBoots('prod');
    }

    #[TestDox('server boots successfully in dev environment')]
    public function test_server_boots_in_dev(): void
    {
        $this->assertServerBoots('dev');
    }

    private function assertServerBoots(string $env): void
    {
        $rootDir = realpath(__DIR__ . '/../../');

        $command = sprintf(
            'php %s serve %s --env=%s --root=%s --port=0 2>&1',
            escapeshellarg(self::BIN_PATH),
            escapeshellarg(self::KERNEL_CLASS),
            escapeshellarg($env),
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
        $deadline = microtime(true) + 10;

        while (microtime(true) < $deadline) {
            $stdout = fread($pipes[1], 8192);
            $stderr = fread($pipes[2], 8192);

            if ($stdout !== false) {
                $output .= $stdout;
            }
            if ($stderr !== false) {
                $output .= $stderr;
            }

            if (str_contains($output, 'Running PHP Language Server')) {
                break;
            }

            $status = proc_get_status($process);
            if (!$status['running'] && !str_contains($output, 'Running PHP Language Server')) {
                fclose($pipes[1]);
                fclose($pipes[2]);
                proc_close($process);
                $this->fail("Server process exited with code {$status['exitcode']} ({$env}). Output:\n{$output}");
            }

            usleep(50_000);
        }

        fclose($pipes[1]);
        fclose($pipes[2]);
        proc_terminate($process, 15);
        proc_close($process);

        $this->assertStringContainsString(
            'Running PHP Language Server',
            $output,
            "Server did not start within 10 seconds ({$env}). Output:\n{$output}",
        );
    }
}

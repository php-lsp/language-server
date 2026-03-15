<?php

/**
 * Benchmark: ReactPHP vs Swoole vs Sync async runners.
 *
 * Simulates the LSP contributor fan-out pattern — multiple tasks running
 * in parallel (or sequentially for sync), each performing a mix of CPU
 * and simulated I/O work.
 *
 * Usage:
 *   php benchmarks/async_runner_benchmark.php [--swoole]
 *
 * The --swoole flag enables the Swoole benchmark (requires ext-swoole).
 * Without the flag, only React and Sync runners are benchmarked.
 */

declare(strict_types=1);

require_once __DIR__ . '/../tests/Support/react_stubs.php';
require_once __DIR__ . '/../vendor/autoload.php';

use App\Module\Async\ReactAsyncRunner;
use App\Module\Async\SwooleAsyncRunner;
use App\Module\Async\SyncAsyncRunner;

// ── Configuration ──────────────────────────────────────────────────────

const ITERATIONS = 5;
const CONTRIBUTOR_COUNTS = [5, 10, 15];

// ── Simulated workloads ────────────────────────────────────────────────

/**
 * Simulates a CPU-bound contributor (e.g. AST parsing, type resolution).
 *
 * @return list<string>
 */
function cpuBoundWork(int $complexity = 1000): array
{
    $results = [];
    for ($i = 0; $i < $complexity; $i++) {
        $results[] = hash('sha256', "item-{$i}-" . random_bytes(16));
    }

    return $results;
}

/**
 * Simulates an I/O-bound contributor (e.g. file reading, index lookup).
 *
 * @return list<string>
 */
function ioBoundWork(int $fileCount = 10): array
{
    $results = [];
    for ($i = 0; $i < $fileCount; $i++) {
        // Simulate file read by reading own source
        $content = file_get_contents(__FILE__);
        $results[] = substr(md5($content . $i), 0, 8);
    }

    return $results;
}

/**
 * Simulates a mixed contributor (typical LSP contributor).
 *
 * @return list<string>
 */
function mixedWork(int $items = 50): array
{
    $results = [];
    // CPU: hash computation
    for ($i = 0; $i < $items; $i++) {
        $results[] = hash('sha256', "mixed-{$i}");
    }
    // I/O: file read
    file_get_contents(__FILE__);

    return $results;
}

// ── Benchmark runner ───────────────────────────────────────────────────

function benchmark(string $label, callable $fn, int $iterations = ITERATIONS): array
{
    $times = [];
    $memoryPeaks = [];

    for ($i = 0; $i < $iterations; $i++) {
        $memBefore = memory_get_usage(true);
        $start = hrtime(true);

        $fn();

        $elapsed = (hrtime(true) - $start) / 1_000_000; // ms
        $memAfter = memory_get_peak_usage(true);

        $times[] = $elapsed;
        $memoryPeaks[] = $memAfter - $memBefore;
    }

    return [
        'label' => $label,
        'avg_ms' => round(array_sum($times) / count($times), 3),
        'min_ms' => round(min($times), 3),
        'max_ms' => round(max($times), 3),
        'median_ms' => round(median($times), 3),
    ];
}

function median(array $values): float
{
    sort($values);
    $count = count($values);
    $mid = (int) ($count / 2);

    if ($count % 2 === 0) {
        return ($values[$mid - 1] + $values[$mid]) / 2;
    }

    return $values[$mid];
}

function printResults(array $results): void
{
    echo str_pad('Runner', 35) . str_pad('Avg (ms)', 12) . str_pad('Min (ms)', 12) . str_pad('Max (ms)', 12) . "Median (ms)\n";
    echo str_repeat('─', 83) . "\n";

    foreach ($results as $r) {
        echo str_pad($r['label'], 35)
            . str_pad((string) $r['avg_ms'], 12)
            . str_pad((string) $r['min_ms'], 12)
            . str_pad((string) $r['max_ms'], 12)
            . $r['median_ms'] . "\n";
    }
}

// ── Main ───────────────────────────────────────────────────────────────

$useSwoole = in_array('--swoole', $argv, true) && SwooleAsyncRunner::isAvailable();

echo "╔══════════════════════════════════════════════════════════════════╗\n";
echo "║  Async Runner Benchmark — ReactPHP vs Sync" . ($useSwoole ? " vs Swoole" : "") . "              ║\n";
echo "║  Iterations per test: " . ITERATIONS . "                                       ║\n";
echo "╚══════════════════════════════════════════════════════════════════╝\n\n";

$syncRunner = new SyncAsyncRunner();
$reactRunner = new ReactAsyncRunner();
$swooleRunner = $useSwoole ? new SwooleAsyncRunner() : null;

foreach (CONTRIBUTOR_COUNTS as $count) {
    echo "── {$count} Contributors ──────────────────────────────────────\n\n";

    // Build tasks
    $buildTasks = function () use ($count): array {
        $tasks = [];
        for ($i = 0; $i < $count; $i++) {
            $type = $i % 3;
            $tasks["contributor_{$i}"] = match ($type) {
                0 => fn () => cpuBoundWork(500),
                1 => fn () => ioBoundWork(5),
                2 => fn () => mixedWork(30),
            };
        }

        return $tasks;
    };

    $results = [];

    // Sync
    $results[] = benchmark("Sync ({$count} contributors)", function () use ($syncRunner, $buildTasks) {
        $syncRunner->parallel($buildTasks());
    });

    // React
    $results[] = benchmark("React ({$count} contributors)", function () use ($reactRunner, $buildTasks) {
        $reactRunner->parallel($buildTasks());
    });

    // Swoole
    if ($swooleRunner !== null) {
        $results[] = benchmark("Swoole ({$count} contributors)", function () use ($swooleRunner, $buildTasks) {
            \Co\run(function () use ($swooleRunner, $buildTasks) {
                $swooleRunner->parallel($buildTasks());
            });
        });
    }

    printResults($results);
    echo "\n";
}

// ── Indexer simulation ─────────────────────────────────────────────────

echo "── Indexer Simulation (100 files) ──────────────────────────────\n\n";

$fileCount = 100;

$buildIndexTasks = function () use ($fileCount): array {
    $tasks = [];
    for ($i = 0; $i < $fileCount; $i++) {
        $tasks["file_{$i}"] = fn () => cpuBoundWork(100);
    }

    return $tasks;
};

$results = [];

$results[] = benchmark('Sync indexing (100 files)', function () use ($syncRunner, $buildIndexTasks) {
    $syncRunner->parallel($buildIndexTasks());
});

$results[] = benchmark('React indexing (100 files)', function () use ($reactRunner, $buildIndexTasks) {
    $reactRunner->parallel($buildIndexTasks());
});

if ($swooleRunner !== null) {
    $results[] = benchmark('Swoole indexing (100 files)', function () use ($swooleRunner, $buildIndexTasks) {
        \Co\run(function () use ($swooleRunner, $buildIndexTasks) {
            $swooleRunner->parallel($buildIndexTasks());
        });
    });
}

printResults($results);
echo "\n";

echo "Done.\n";

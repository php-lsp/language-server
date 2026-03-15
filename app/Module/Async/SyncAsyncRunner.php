<?php

declare(strict_types=1);

namespace App\Module\Async;

use App\Core\Contracts\Async\AsyncRunnerInterface;

/**
 * Synchronous fallback runner.
 *
 * Executes tasks sequentially. Useful for testing and environments
 * without async extensions.
 */
final class SyncAsyncRunner implements AsyncRunnerInterface
{
    #[\Override]
    public function parallel(array $tasks, float $timeout = 0): array
    {
        $results = [];

        foreach ($tasks as $name => $task) {
            $start = microtime(true);
            $results[$name] = $task();

            if ($timeout > 0 && (microtime(true) - $start) > $timeout) {
                throw new \RuntimeException(
                    "Task '{$name}' exceeded timeout of {$timeout}s",
                );
            }
        }

        return $results;
    }

    #[\Override]
    public function run(callable $task, float $timeout = 0): mixed
    {
        return $task();
    }
}

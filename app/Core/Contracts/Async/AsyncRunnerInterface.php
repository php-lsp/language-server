<?php

declare(strict_types=1);

namespace App\Core\Contracts\Async;

/**
 * Abstracts parallel task execution.
 *
 * Implementations can use ReactPHP promises, Swoole coroutines,
 * or any other concurrency mechanism.
 */
interface AsyncRunnerInterface
{
    /**
     * Run multiple callables in parallel and return their results.
     *
     * @param array<string, callable(): mixed> $tasks Named callables
     * @param float $timeout Maximum execution time in seconds (0 = no limit)
     * @return array<string, mixed> Results keyed by task name
     */
    public function parallel(array $tasks, float $timeout = 0): array;

    /**
     * Run a single callable asynchronously within the current context.
     *
     * @template T
     * @param callable(): T $task
     * @param float $timeout Maximum execution time in seconds (0 = no limit)
     * @return T
     */
    public function run(callable $task, float $timeout = 0): mixed;
}

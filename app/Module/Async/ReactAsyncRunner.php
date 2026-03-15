<?php

declare(strict_types=1);

namespace App\Module\Async;

use App\Core\Contracts\Async\AsyncRunnerInterface;

use function React\Async\async;
use function React\Async\await;
use function React\Promise\all;
use function React\Promise\Timer\timeout;

/**
 * ReactPHP-based async runner.
 *
 * Uses React promises to fan out tasks. Under ReactPHP's single-threaded
 * event loop, tasks yield cooperatively only on explicit I/O or delay(0)
 * calls — CPU-bound work runs sequentially.
 */
final class ReactAsyncRunner implements AsyncRunnerInterface
{
    #[\Override]
    public function parallel(array $tasks, float $timeout = 0): array
    {
        $promises = [];

        foreach ($tasks as $name => $task) {
            $promise = async($task)();

            if ($timeout > 0) {
                $promise = timeout($promise, $timeout);
            }

            $promises[$name] = $promise;
        }

        return await(all($promises));
    }

    #[\Override]
    public function run(callable $task, float $timeout = 0): mixed
    {
        $promise = async($task)();

        if ($timeout > 0) {
            $promise = timeout($promise, $timeout);
        }

        return await($promise);
    }
}

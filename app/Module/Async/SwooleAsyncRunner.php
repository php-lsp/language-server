<?php

declare(strict_types=1);

namespace App\Module\Async;

use App\Core\Contracts\Async\AsyncRunnerInterface;

/**
 * Swoole coroutine-based async runner.
 *
 * Uses Swoole's coroutines for true parallel execution. Unlike ReactPHP,
 * Swoole coroutines yield automatically on any I/O operation (file reads,
 * network, etc.) without explicit cooperation from the task code.
 *
 * Requirements:
 * - ext-swoole >= 6.0
 * - Must be called within a Swoole coroutine context (Co\run or server worker)
 *
 * @see https://wiki.swoole.com/en/#/coroutine
 */
final class SwooleAsyncRunner implements AsyncRunnerInterface
{
    #[\Override]
    public function parallel(array $tasks, float $timeout = 0): array
    {
        if (!self::isAvailable()) {
            throw new \RuntimeException(
                'Swoole extension is not loaded. Install ext-swoole >= 6.0.',
            );
        }

        /** @var array<string, mixed> $results */
        $results = [];
        /** @var array<string, \Throwable> $errors */
        $errors = [];

        // Use Swoole WaitGroup + Channel for parallel execution
        $wg = new \Swoole\Coroutine\WaitGroup();
        $channel = new \Swoole\Coroutine\Channel(count($tasks));

        foreach ($tasks as $name => $task) {
            $wg->add();

            \Swoole\Coroutine::create(static function () use ($name, $task, $wg, $channel, $timeout) {
                try {
                    if ($timeout > 0) {
                        \Swoole\Coroutine::setTimeLimit($timeout);
                    }

                    $result = $task();
                    $channel->push(['name' => $name, 'result' => $result, 'error' => null]);
                } catch (\Throwable $e) {
                    $channel->push(['name' => $name, 'result' => null, 'error' => $e]);
                } finally {
                    $wg->done();
                }
            });
        }

        $wg->wait($timeout > 0 ? $timeout : -1);
        $channel->close();

        // Drain channel
        for ($i = 0, $count = count($tasks); $i < $count; $i++) {
            $data = $channel->pop(0.001);

            if ($data === false) {
                break;
            }

            if ($data['error'] !== null) {
                $errors[$data['name']] = $data['error'];

                continue;
            }

            $results[$data['name']] = $data['result'];
        }

        if ($errors !== []) {
            $first = reset($errors);
            throw $first;
        }

        return $results;
    }

    #[\Override]
    public function run(callable $task, float $timeout = 0): mixed
    {
        if (!self::isAvailable()) {
            throw new \RuntimeException(
                'Swoole extension is not loaded. Install ext-swoole >= 6.0.',
            );
        }

        if ($timeout > 0) {
            \Swoole\Coroutine::setTimeLimit($timeout);
        }

        return $task();
    }

    public static function isAvailable(): bool
    {
        return extension_loaded('swoole');
    }
}

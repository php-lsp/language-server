<?php

/**
 * Stub implementations of React async functions for testing.
 */

namespace React\Async {
    if (!function_exists('React\Async\await')) {
        function await($promise) {
            if ($promise instanceof \React\Promise\PromiseInterface) {
                $result = null;
                $exception = null;
                $promise->then(
                    function ($value) use (&$result) { $result = $value; },
                    function ($reason) use (&$exception) {
                        $exception = $reason instanceof \Throwable
                            ? $reason
                            : new \RuntimeException((string) $reason);
                    }
                );
                if ($exception) throw $exception;
                return $result;
            }
            return $promise;
        }
    }
    if (!function_exists('React\Async\async')) {
        function async(callable $fn): callable {
            return function () use ($fn) {
                $args = func_get_args();
                try {
                    $result = $fn(...$args);
                    return \React\Promise\resolve($result);
                } catch (\Throwable $e) {
                    return \React\Promise\reject($e);
                }
            };
        }
    }
    if (!function_exists('React\Async\delay')) {
        function delay(float $seconds): void {
            // no-op in tests
        }
    }
}

namespace React\Promise\Timer {
    if (!function_exists('React\Promise\Timer\timeout')) {
        function timeout($promise, float $time) {
            return $promise;
        }
    }
}

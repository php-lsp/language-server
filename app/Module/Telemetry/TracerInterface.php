<?php

declare(strict_types=1);

namespace App\Module\Telemetry;

/**
 * Provides application-level tracing for LSP request processing.
 *
 * Wraps callables in spans that are exported to an OTLP-compatible backend
 * (SigNoz, Jaeger, etc.) when configured, or silently no-ops otherwise.
 */
interface TracerInterface
{
    /**
     * Executes the callback inside a named span.
     *
     * Nested calls create child spans automatically via OpenTelemetry
     * context propagation. Exceptions are recorded on the span and re-thrown.
     *
     * @template T
     *
     * @param callable(): T $callback
     * @param array<string, string|int|float|bool> $attributes
     *
     * @return T
     */
    public function trace(string $name, callable $callback, array $attributes = []): mixed;
}

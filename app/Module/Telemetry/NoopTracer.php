<?php

declare(strict_types=1);

namespace App\Module\Telemetry;

use Override;

/**
 * No-op tracer used when APM is disabled or OpenTelemetry SDK is not installed.
 *
 * Simply executes the callback without any tracing overhead.
 */
final class NoopTracer implements TracerInterface
{
    #[Override]
    public function trace(string $name, callable $callback, array $attributes = []): mixed
    {
        return $callback();
    }
}

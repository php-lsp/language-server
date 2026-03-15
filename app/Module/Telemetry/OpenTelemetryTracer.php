<?php

declare(strict_types=1);

namespace App\Module\Telemetry;

use OpenTelemetry\API\Trace\SpanKind;
use OpenTelemetry\API\Trace\StatusCode;
use OpenTelemetry\API\Trace\TracerInterface as OTelTracerInterface;
use Override;

/**
 * Tracer backed by the OpenTelemetry SDK.
 *
 * Creates spans that are exported to an OTLP-compatible backend (SigNoz,
 * Jaeger, Grafana Tempo, etc.). Span nesting is handled automatically via
 * OpenTelemetry's context propagation.
 */
final class OpenTelemetryTracer implements TracerInterface
{
    public function __construct(
        private readonly OTelTracerInterface $tracer,
    ) {}

    #[Override]
    public function trace(string $name, callable $callback, array $attributes = []): mixed
    {
        $spanBuilder = $this->tracer
            ->spanBuilder($name !== '' ? $name : 'unknown')
            ->setSpanKind(SpanKind::KIND_INTERNAL);

        foreach ($attributes as $key => $value) {
            $spanBuilder->setAttribute($key, $value);
        }

        $span = $spanBuilder->startSpan();
        $scope = $span->activate();

        try {
            $result = $callback();
            $span->setStatus(StatusCode::STATUS_OK);

            return $result;
        } catch (\Throwable $e) {
            $span->setStatus(StatusCode::STATUS_ERROR, $e->getMessage());
            $span->recordException($e);

            throw $e;
        } finally {
            $span->end();
            $scope->detach();
        }
    }
}

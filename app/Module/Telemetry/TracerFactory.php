<?php

declare(strict_types=1);

namespace App\Module\Telemetry;

use OpenTelemetry\API\Trace\TracerInterface as OTelTracerInterface;
use OpenTelemetry\Contrib\Otlp\OtlpHttpTransportFactory;
use OpenTelemetry\Contrib\Otlp\SpanExporter;
use OpenTelemetry\SDK\Common\Attribute\Attributes;
use OpenTelemetry\SDK\Resource\ResourceInfo;
use OpenTelemetry\SDK\Resource\ResourceInfoFactory;
use OpenTelemetry\SDK\Trace\SpanProcessor\BatchSpanProcessorBuilder;
use OpenTelemetry\SDK\Trace\TracerProvider;
use OpenTelemetry\SemConv\ResourceAttributes;

/**
 * Creates a configured {@see TracerInterface} based on environment settings.
 *
 * When the OTLP endpoint is set (via `OTEL_EXPORTER_OTLP_ENDPOINT` env var),
 * creates an {@see OpenTelemetryTracer} that exports spans to SigNoz / Jaeger.
 * Otherwise, returns a {@see NoopTracer}.
 */
final class TracerFactory
{
    private ?TracerProvider $tracerProvider = null;

    public function __construct(
        private readonly string $endpoint,
        private readonly string $serviceName,
    ) {}

    public function create(): TracerInterface
    {
        if ($this->endpoint === '' || !$this->isOTelInstalled()) {
            return new NoopTracer();
        }

        return new OpenTelemetryTracer($this->createOTelTracer());
    }

    /**
     * Shuts down the tracer provider, flushing all pending spans.
     *
     * Should be called on server shutdown to ensure all spans are exported.
     */
    public function shutdown(): void
    {
        $this->tracerProvider?->shutdown();
    }

    private function createOTelTracer(): OTelTracerInterface
    {
        $transport = new OtlpHttpTransportFactory()->create(
            endpoint: \rtrim($this->endpoint, characters: '/') . '/v1/traces',
            contentType: 'application/json',
        );

        $exporter = new SpanExporter($transport);

        $resource = ResourceInfoFactory::defaultResource()->merge(
            ResourceInfo::create(Attributes::create([
                ResourceAttributes::SERVICE_NAME => $this->serviceName,
                ResourceAttributes::SERVICE_VERSION => '0.0.1',
            ])),
        );

        $spanProcessor = new BatchSpanProcessorBuilder($exporter)->build();

        $this->tracerProvider = new TracerProvider(
            spanProcessors: $spanProcessor,
            resource: $resource,
        );

        return $this->tracerProvider->getTracer('php-lsp');
    }

    private function isOTelInstalled(): bool
    {
        return \class_exists(TracerProvider::class) && \class_exists(SpanExporter::class);
    }
}

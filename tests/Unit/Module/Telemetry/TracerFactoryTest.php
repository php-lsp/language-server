<?php

declare(strict_types=1);

namespace App\Tests\Unit\Module\Telemetry;

use App\Module\Telemetry\NoopTracer;
use App\Module\Telemetry\OpenTelemetryTracer;
use App\Module\Telemetry\TracerFactory;
use App\Tests\TestCase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\TestDox;

#[Group('unit')]
final class TracerFactoryTest extends TestCase
{
    #[TestDox('should create NoopTracer when endpoint is empty')]
    public function testCreatesNoopTracerWhenEndpointEmpty(): void
    {
        $factory = new TracerFactory('', 'test-service');

        $tracer = $factory->create();

        $this->assertInstanceOf(NoopTracer::class, $tracer);
    }

    #[TestDox('should create OpenTelemetryTracer when endpoint is set and OTel is installed')]
    public function testCreatesOpenTelemetryTracerWhenConfigured(): void
    {
        $factory = new TracerFactory('http://localhost:4318', 'test-service');

        $tracer = $factory->create();

        $this->assertInstanceOf(OpenTelemetryTracer::class, $tracer);
    }

    #[TestDox('should allow shutdown without errors when no provider was created')]
    public function testShutdownWithoutProvider(): void
    {
        $factory = new TracerFactory('', 'test-service');
        $factory->create();

        // Should not throw
        $factory->shutdown();

        $this->assertTrue(true);
    }
}

<?php

declare(strict_types=1);

namespace App\Tests\Unit\Module\Telemetry;

use App\Module\Telemetry\OpenTelemetryTracer;
use App\Tests\TestCase;
use OpenTelemetry\API\Trace\SpanBuilderInterface;
use OpenTelemetry\API\Trace\SpanInterface;
use OpenTelemetry\API\Trace\SpanKind;
use OpenTelemetry\API\Trace\StatusCode;
use OpenTelemetry\API\Trace\TracerInterface as OTelTracerInterface;
use OpenTelemetry\Context\ScopeInterface;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\TestDox;

#[Group('unit')]
final class OpenTelemetryTracerTest extends TestCase
{
    #[TestDox('should create a span and return the callback result')]
    public function testCreatesSpanAndReturnsResult(): void
    {
        $scope = $this->createMock(ScopeInterface::class);
        $scope->expects($this->once())->method('detach');

        $span = $this->createMock(SpanInterface::class);
        $span->expects($this->once())->method('activate')->willReturn($scope);
        $span->expects($this->once())->method('setStatus')->with(StatusCode::STATUS_OK);
        $span->expects($this->once())->method('end');

        $spanBuilder = $this->createMock(SpanBuilderInterface::class);
        $spanBuilder->method('setSpanKind')->with(SpanKind::KIND_INTERNAL)->willReturnSelf();
        $spanBuilder->method('startSpan')->willReturn($span);

        $otelTracer = $this->createMock(OTelTracerInterface::class);
        $otelTracer->method('spanBuilder')->with('test-operation')->willReturn($spanBuilder);

        $tracer = new OpenTelemetryTracer($otelTracer);
        $result = $tracer->trace('test-operation', static fn() => 'hello');

        $this->assertSame('hello', $result);
    }

    #[TestDox('should record exception and set error status on failure')]
    public function testRecordsExceptionOnFailure(): void
    {
        $exception = new \RuntimeException('something broke');

        $scope = $this->createMock(ScopeInterface::class);
        $scope->expects($this->once())->method('detach');

        $span = $this->createMock(SpanInterface::class);
        $span->expects($this->once())->method('activate')->willReturn($scope);
        $span->expects($this->once())->method('setStatus')->with(StatusCode::STATUS_ERROR, 'something broke');
        $span->expects($this->once())->method('recordException')->with($exception);
        $span->expects($this->once())->method('end');

        $spanBuilder = $this->createMock(SpanBuilderInterface::class);
        $spanBuilder->method('setSpanKind')->willReturnSelf();
        $spanBuilder->method('startSpan')->willReturn($span);

        $otelTracer = $this->createMock(OTelTracerInterface::class);
        $otelTracer->method('spanBuilder')->willReturn($spanBuilder);

        $tracer = new OpenTelemetryTracer($otelTracer);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('something broke');

        $tracer->trace('failing-operation', static function () use ($exception) {
            throw $exception;
        });
    }

    #[TestDox('should set attributes on the span')]
    public function testSetsAttributes(): void
    {
        $scope = $this->createMock(ScopeInterface::class);

        $span = $this->createMock(SpanInterface::class);
        $span->method('activate')->willReturn($scope);
        $span->method('setStatus');
        $span->method('end');

        $spanBuilder = $this->createMock(SpanBuilderInterface::class);
        $spanBuilder->method('setSpanKind')->willReturnSelf();
        $spanBuilder->expects($this->exactly(2))->method('setAttribute')
            ->willReturnSelf();
        $spanBuilder->method('startSpan')->willReturn($span);

        $otelTracer = $this->createMock(OTelTracerInterface::class);
        $otelTracer->method('spanBuilder')->willReturn($spanBuilder);

        $tracer = new OpenTelemetryTracer($otelTracer);
        $tracer->trace('op', static fn() => null, ['key1' => 'val1', 'key2' => 42]);
    }
}

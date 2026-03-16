<?php

declare(strict_types=1);

namespace App\Tests\Unit\Module\Telemetry;

use App\Module\Telemetry\NoopTracer;
use App\Tests\TestCase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\TestDox;

#[Group('unit')]
final class NoopTracerTest extends TestCase
{
    #[TestDox('should execute callback and return its result')]
    public function testReturnsCallbackResult(): void
    {
        $tracer = new NoopTracer();

        $result = $tracer->trace('test-span', static fn() => 42);

        $this->assertSame(42, $result);
    }

    #[TestDox('should propagate exceptions from callback')]
    public function testPropagatesExceptions(): void
    {
        $tracer = new NoopTracer();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('test error');

        $tracer->trace('test-span', static function () {
            throw new \RuntimeException('test error');
        });
    }

    #[TestDox('should support nested trace calls')]
    public function testNestedTraceCalls(): void
    {
        $tracer = new NoopTracer();
        $sequence = [];

        $tracer->trace('outer', function () use ($tracer, &$sequence) {
            $sequence[] = 'outer-start';
            $tracer->trace('inner', function () use (&$sequence) {
                $sequence[] = 'inner';
            });
            $sequence[] = 'outer-end';
        });

        $this->assertSame(['outer-start', 'inner', 'outer-end'], $sequence);
    }

    #[TestDox('should ignore attributes without affecting execution')]
    public function testIgnoresAttributes(): void
    {
        $tracer = new NoopTracer();

        $result = $tracer->trace('test', static fn() => 'ok', [
            'key' => 'value',
            'count' => 42,
        ]);

        $this->assertSame('ok', $result);
    }
}

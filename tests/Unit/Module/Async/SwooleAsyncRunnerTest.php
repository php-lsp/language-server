<?php

declare(strict_types=1);

namespace App\Tests\Unit\Module\Async;

use App\Module\Async\SwooleAsyncRunner;
use App\Tests\TestCase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\TestDox;

#[Group('unit')]
#[TestDox('SwooleAsyncRunner')]
final class SwooleAsyncRunnerTest extends TestCase
{
    #[TestDox('isAvailable returns false when extension not loaded')]
    public function testIsAvailableReturnsFalseWithoutExtension(): void
    {
        if (extension_loaded('swoole')) {
            $this->markTestSkipped('Swoole is loaded — cannot test unavailability');
        }

        $this->assertFalse(SwooleAsyncRunner::isAvailable());
    }

    #[TestDox('parallel throws when Swoole not available')]
    public function testParallelThrowsWithoutSwoole(): void
    {
        if (extension_loaded('swoole')) {
            $this->markTestSkipped('Swoole is loaded');
        }

        $runner = new SwooleAsyncRunner();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Swoole extension is not loaded');

        $runner->parallel(['task' => fn () => 42]);
    }

    #[TestDox('run throws when Swoole not available')]
    public function testRunThrowsWithoutSwoole(): void
    {
        if (extension_loaded('swoole')) {
            $this->markTestSkipped('Swoole is loaded');
        }

        $runner = new SwooleAsyncRunner();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Swoole extension is not loaded');

        $runner->run(fn () => 42);
    }
}

<?php

declare(strict_types=1);

namespace App\Tests\Support;

use App\Core\Contracts\Async\AsyncRunnerInterface;
use App\Tests\TestCase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\TestDox;

/**
 * Contract tests that any AsyncRunnerInterface implementation must satisfy.
 * Subclass this and implement createRunner() for each implementation.
 */
#[Group('unit')]
abstract class AsyncRunnerContractTest extends TestCase
{
    abstract protected function createRunner(): AsyncRunnerInterface;

    #[TestDox('parallel returns empty array for no tasks')]
    public function testParallelEmptyTasks(): void
    {
        $runner = $this->createRunner();
        $results = $runner->parallel([]);

        $this->assertSame([], $results);
    }

    #[TestDox('parallel executes single task')]
    public function testParallelSingleTask(): void
    {
        $runner = $this->createRunner();
        $results = $runner->parallel([
            'task1' => fn () => 42,
        ]);

        $this->assertSame(['task1' => 42], $results);
    }

    #[TestDox('parallel executes multiple tasks and preserves keys')]
    public function testParallelMultipleTasks(): void
    {
        $runner = $this->createRunner();
        $results = $runner->parallel([
            'a' => fn () => 'alpha',
            'b' => fn () => 'beta',
            'c' => fn () => 'gamma',
        ]);

        $this->assertSame([
            'a' => 'alpha',
            'b' => 'beta',
            'c' => 'gamma',
        ], $results);
    }

    #[TestDox('parallel propagates exceptions')]
    public function testParallelPropagatesException(): void
    {
        $runner = $this->createRunner();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('task failed');

        $runner->parallel([
            'failing' => fn () => throw new \RuntimeException('task failed'),
        ]);
    }

    #[TestDox('run executes a single callable')]
    public function testRunSingleCallable(): void
    {
        $runner = $this->createRunner();
        $result = $runner->run(fn () => 'hello');

        $this->assertSame('hello', $result);
    }

    #[TestDox('run propagates exceptions')]
    public function testRunPropagatesException(): void
    {
        $runner = $this->createRunner();

        $this->expectException(\RuntimeException::class);
        $runner->run(fn () => throw new \RuntimeException('fail'));
    }

    #[TestDox('parallel collects results from tasks that produce arrays')]
    public function testParallelWithArrayResults(): void
    {
        $runner = $this->createRunner();
        $results = $runner->parallel([
            'items' => fn () => ['foo', 'bar'],
            'counts' => fn () => ['x' => 1, 'y' => 2],
        ]);

        $this->assertSame(['foo', 'bar'], $results['items']);
        $this->assertSame(['x' => 1, 'y' => 2], $results['counts']);
    }
}

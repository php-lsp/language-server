<?php

declare(strict_types=1);

namespace App\Tests\Unit\Module\Async;

use App\Core\Contracts\Async\AsyncRunnerInterface;
use App\Module\Async\ReactAsyncRunner;
use App\Tests\Support\AsyncRunnerContractTest;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\TestDox;

#[Group('unit')]
#[TestDox('ReactAsyncRunner')]
final class ReactAsyncRunnerTest extends AsyncRunnerContractTest
{
    protected function createRunner(): AsyncRunnerInterface
    {
        return new ReactAsyncRunner();
    }

    #[TestDox('handles mixed success and failure tasks')]
    public function testMixedSuccessAndFailure(): void
    {
        $runner = new ReactAsyncRunner();

        $this->expectException(\RuntimeException::class);

        $runner->parallel([
            'ok' => fn () => 'success',
            'fail' => fn () => throw new \RuntimeException('boom'),
        ]);
    }
}

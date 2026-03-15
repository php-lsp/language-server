<?php

declare(strict_types=1);

namespace App\Tests\Unit\Module\Async;

use App\Core\Contracts\Async\AsyncRunnerInterface;
use App\Module\Async\SyncAsyncRunner;
use App\Tests\Support\AsyncRunnerContractTest;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\TestDox;

#[Group('unit')]
#[TestDox('SyncAsyncRunner')]
final class SyncAsyncRunnerTest extends AsyncRunnerContractTest
{
    protected function createRunner(): AsyncRunnerInterface
    {
        return new SyncAsyncRunner();
    }

    #[TestDox('executes tasks sequentially')]
    public function testExecutesSequentially(): void
    {
        $runner = new SyncAsyncRunner();
        $order = [];

        $runner->parallel([
            'first' => function () use (&$order) {
                $order[] = 'first';

                return 1;
            },
            'second' => function () use (&$order) {
                $order[] = 'second';

                return 2;
            },
            'third' => function () use (&$order) {
                $order[] = 'third';

                return 3;
            },
        ]);

        $this->assertSame(['first', 'second', 'third'], $order);
    }
}

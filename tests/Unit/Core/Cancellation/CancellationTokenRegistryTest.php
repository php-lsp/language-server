<?php

declare(strict_types=1);

namespace App\Tests\Unit\Core\Cancellation;

use App\Core\Cancellation\CancellationTokenRegistry;
use App\Tests\TestCase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\TestDox;

#[Group('unit')]
final class CancellationTokenRegistryTest extends TestCase
{
    #[TestDox('can create and cancel a token')]
    public function testCreateAndCancel(): void
    {
        $registry = new CancellationTokenRegistry();
        $token = $registry->create(42);

        $this->assertFalse($token->isCancelled());

        $registry->cancel(42);

        $this->assertTrue($token->isCancelled());
    }

    #[TestDox('cancelling non-existent request does nothing')]
    public function testCancelNonExistent(): void
    {
        $registry = new CancellationTokenRegistry();
        $registry->cancel(999);

        $this->assertTrue(true);
    }

    #[TestDox('can remove a token')]
    public function testRemoveToken(): void
    {
        $registry = new CancellationTokenRegistry();
        $token = $registry->create(1);

        $registry->remove(1);
        $registry->cancel(1);

        $this->assertFalse($token->isCancelled());
    }

    #[TestDox('supports string request IDs')]
    public function testStringRequestId(): void
    {
        $registry = new CancellationTokenRegistry();
        $token = $registry->create('req-1');

        $registry->cancel('req-1');

        $this->assertTrue($token->isCancelled());
    }

    #[TestDox('cancel also removes the token from registry')]
    public function testCancelRemovesToken(): void
    {
        $registry = new CancellationTokenRegistry();
        $registry->create(1);

        $this->assertSame(1, $registry->count());

        $registry->cancel(1);

        $this->assertSame(0, $registry->count());
    }

    #[TestDox('evicts oldest tokens when exceeding max capacity')]
    public function testEvictsOldestTokens(): void
    {
        $registry = new CancellationTokenRegistry();

        for ($i = 0; $i < 1000; $i++) {
            $registry->create($i);
        }

        $this->assertSame(1000, $registry->count());

        $registry->create(1001);

        $this->assertLessThanOrEqual(1000, $registry->count());
    }

    #[TestDox('count returns number of active tokens')]
    public function testCount(): void
    {
        $registry = new CancellationTokenRegistry();

        $this->assertSame(0, $registry->count());

        $registry->create(1);
        $registry->create(2);

        $this->assertSame(2, $registry->count());

        $registry->remove(1);

        $this->assertSame(1, $registry->count());
    }
}

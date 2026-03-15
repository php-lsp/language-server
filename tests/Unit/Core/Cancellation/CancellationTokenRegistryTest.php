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
}

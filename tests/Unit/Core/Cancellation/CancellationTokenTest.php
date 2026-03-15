<?php

declare(strict_types=1);

namespace App\Tests\Unit\Core\Cancellation;

use App\Core\Cancellation\CancellationToken;
use App\Tests\TestCase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\TestDox;

#[Group('unit')]
final class CancellationTokenTest extends TestCase
{
    #[TestDox('new token is not cancelled')]
    public function testNewTokenIsNotCancelled(): void
    {
        $token = new CancellationToken();
        $this->assertFalse($token->isCancelled());
    }

    #[TestDox('token can be cancelled')]
    public function testTokenCanBeCancelled(): void
    {
        $token = new CancellationToken();
        $token->cancel();
        $this->assertTrue($token->isCancelled());
    }
}

<?php

declare(strict_types=1);

namespace App\Tests\Unit\Module\Notification;

use App\Module\Notification\Result;
use App\Tests\TestCase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\TestDox;

#[Group('unit')]
final class ResultTest extends TestCase
{
    #[TestDox('success factory creates successful result')]
    public function testSuccess(): void
    {
        $result = Result::success();

        $this->assertTrue($result->isSuccess());
        $this->assertFalse($result->isError());
        $this->assertNull($result->getError());
        $this->assertTrue($result->success);
    }

    #[TestDox('error factory creates failed result with message')]
    public function testError(): void
    {
        $result = Result::error('something broke');

        $this->assertFalse($result->isSuccess());
        $this->assertTrue($result->isError());
        $this->assertSame('something broke', $result->getError());
        $this->assertSame('something broke', $result->error);
    }
}

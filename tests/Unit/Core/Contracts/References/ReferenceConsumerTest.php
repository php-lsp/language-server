<?php

declare(strict_types=1);

namespace App\Tests\Unit\Core\Contracts\References;

use App\Core\Contracts\References\ReferenceConsumer;
use App\Tests\Support\ProtocolFactory;
use App\Tests\TestCase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\TestDox;

#[Group('unit')]
final class ReferenceConsumerTest extends TestCase
{
    #[TestDox('collects location items')]
    public function testCollectsItems(): void
    {
        $consumer = new ReferenceConsumer();
        $consumer(ProtocolFactory::location());

        $this->assertCount(1, $consumer->results);
    }

    #[TestDox('starts with empty results')]
    public function testStartsEmpty(): void
    {
        $this->assertSame([], (new ReferenceConsumer())->results);
    }
}

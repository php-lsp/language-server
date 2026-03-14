<?php

declare(strict_types=1);

namespace App\Tests\Unit\Core\Contracts\Declaration;

use App\Core\Contracts\Declaration\DeclarationConsumer;
use App\Tests\Support\ProtocolFactory;
use App\Tests\TestCase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\TestDox;

#[Group('unit')]
final class DeclarationConsumerTest extends TestCase
{
    #[TestDox('collects location items')]
    public function testCollectsItems(): void
    {
        $consumer = new DeclarationConsumer();
        $location = ProtocolFactory::location();

        $consumer($location);

        $this->assertCount(1, $consumer->results);
    }

    #[TestDox('collects multiple items')]
    public function testCollectsMultiple(): void
    {
        $consumer = new DeclarationConsumer();

        $consumer(ProtocolFactory::location('file:///a.php'), ProtocolFactory::location('file:///b.php'));

        $this->assertCount(2, $consumer->results);
    }

    #[TestDox('starts with empty results')]
    public function testStartsEmpty(): void
    {
        $this->assertSame([], (new DeclarationConsumer())->results);
    }
}

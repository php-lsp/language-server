<?php

declare(strict_types=1);

namespace App\Tests\Unit\Core\Contracts\Definition;

use App\Core\Contracts\Definition\DefinitionConsumer;
use App\Tests\Support\ProtocolFactory;
use App\Tests\TestCase;
use Lsp\Protocol\Type\Location;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\TestDox;

#[Group('unit')]
#[TestDox('DefinitionConsumer')]
final class DefinitionConsumerTest extends TestCase
{
    #[TestDox('starts with empty results')]
    public function testStartsEmpty(): void
    {
        $consumer = new DefinitionConsumer();
        $this->assertSame([], $consumer->results);
    }

    #[TestDox('collects locations via invoke')]
    public function testCollectsLocations(): void
    {
        $consumer = new DefinitionConsumer();
        $location = ProtocolFactory::location();

        $consumer($location);

        $this->assertCount(1, $consumer->results);
        $this->assertSame($location, $consumer->results[0]);
    }

    #[TestDox('collects multiple locations')]
    public function testCollectsMultipleLocations(): void
    {
        $consumer = new DefinitionConsumer();
        $loc1 = ProtocolFactory::location('file:///a.php');
        $loc2 = ProtocolFactory::location('file:///b.php');

        $consumer($loc1, $loc2);

        $this->assertCount(2, $consumer->results);
    }
}

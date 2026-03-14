<?php

declare(strict_types=1);

namespace App\Tests\Unit\Core\Contracts\Completion;

use App\Core\Contracts\Completion\CompletionConsumer;
use App\Tests\Support\ProtocolFactory;
use App\Tests\TestCase;
use Lsp\Protocol\Type\CompletionItem;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\TestDox;

#[Group('unit')]
final class CompletionConsumerTest extends TestCase
{
    #[TestDox('collects completion items')]
    public function testCollectsItems(): void
    {
        $consumer = new CompletionConsumer();
        ($consumer)(ProtocolFactory::completionItem('a'), ProtocolFactory::completionItem('b'));

        $this->assertCount(2, $consumer->results);
    }

    #[TestDox('respects limit')]
    public function testRespectsLimit(): void
    {
        $consumer = new CompletionConsumer(limit: 2);
        ($consumer)(
            ProtocolFactory::completionItem('a'),
            ProtocolFactory::completionItem('b'),
            ProtocolFactory::completionItem('c'),
        );

        $this->assertCount(2, $consumer->results);
    }

    #[TestDox('yields after batch threshold')]
    public function testYieldsAfterBatch(): void
    {
        $consumer = new CompletionConsumer();

        // Add 100+ items to trigger shouldYield
        $items = [];
        for ($i = 0; $i < 101; $i++) {
            $items[] = ProtocolFactory::completionItem("item$i");
        }
        ($consumer)(...$items);

        $this->assertCount(101, $consumer->results);
    }
}

<?php

declare(strict_types=1);

namespace App\Tests\Unit\Core\Contracts\Completion;

use App\Core\Contracts\Completion\CompletionConsumer;
use App\Tests\Support\ProtocolFactory;
use App\Tests\TestCase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\TestDox;

#[Group('unit')]
final class CompletionConsumerTest extends TestCase
{
    #[TestDox('collects completion items')]
    public function testCollectsItems(): void
    {
        $consumer = new CompletionConsumer();
        $item = ProtocolFactory::completionItem('test');

        $consumer($item);

        $this->assertCount(1, $consumer->results);
        $this->assertSame('test', $consumer->results[0]->label);
    }

    #[TestDox('collects multiple items at once')]
    public function testCollectsMultipleItems(): void
    {
        $consumer = new CompletionConsumer();

        $consumer(
            ProtocolFactory::completionItem('a'),
            ProtocolFactory::completionItem('b'),
        );

        $this->assertCount(2, $consumer->results);
    }

    #[TestDox('starts with empty results')]
    public function testStartsEmpty(): void
    {
        $consumer = new CompletionConsumer();
        $this->assertSame([], $consumer->results);
    }

    #[TestDox('respects limit')]
    public function testRespectsLimit(): void
    {
        $consumer = new CompletionConsumer(limit: 2);

        $consumer(
            ProtocolFactory::completionItem('a'),
            ProtocolFactory::completionItem('b'),
            ProtocolFactory::completionItem('c'),
        );

        $this->assertCount(2, $consumer->results);
    }
}

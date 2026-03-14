<?php

declare(strict_types=1);

namespace App\Tests\Unit\Core\Contracts\Documentation;

use App\Core\Contracts\Documentation\DocumentationConsumer;
use App\Tests\TestCase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\TestDox;

#[Group('unit')]
final class DocumentationConsumerTest extends TestCase
{
    #[TestDox('collects string items')]
    public function testCollectsItems(): void
    {
        $consumer = new DocumentationConsumer();
        $consumer('hello', 'world');

        $this->assertSame(['hello', 'world'], $consumer->results);
    }

    #[TestDox('starts with empty results')]
    public function testStartsEmpty(): void
    {
        $this->assertSame([], (new DocumentationConsumer())->results);
    }
}

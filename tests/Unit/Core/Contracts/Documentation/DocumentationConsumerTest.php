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
    #[TestDox('collects documentation strings')]
    public function testCollectsStrings(): void
    {
        $consumer = new DocumentationConsumer();
        ($consumer)('line 1', 'line 2');

        $this->assertSame(['line 1', 'line 2'], $consumer->results);
    }
}

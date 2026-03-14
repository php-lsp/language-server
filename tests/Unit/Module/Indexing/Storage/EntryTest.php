<?php

declare(strict_types=1);

namespace App\Tests\Unit\Module\Indexing\Storage;

use App\Module\Indexing\Storage\Entry;
use App\Tests\TestCase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\TestDox;

#[Group('unit')]
final class EntryTest extends TestCase
{
    #[TestDox('exposes readonly properties')]
    public function testProperties(): void
    {
        $entry = new Entry('myKey', ['data'], 'file:///test.php');

        $this->assertSame('myKey', $entry->key);
        $this->assertSame(['data'], $entry->value);
        $this->assertSame('file:///test.php', $entry->uri);
    }
}

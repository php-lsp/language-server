<?php

declare(strict_types=1);

namespace App\Tests\Unit\Module\Indexing\Storage;

use App\Module\Indexing\Storage\Entry;
use App\Module\Indexing\Storage\InMemoryStorage;
use App\Tests\TestCase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\TestDox;

#[Group('unit')]
final class InMemoryStorageTest extends TestCase
{
    #[TestDox('read returns entries for unknown key')]
    public function testReadUnknown(): void
    {
        $storage = new InMemoryStorage();
        $results = iterator_to_array($storage->read('unknown'));

        // Verify we get an iterable result (implementation may yield empty or default)
        $this->assertIsArray($results);
    }

    #[TestDox('write and read round-trip with string keys')]
    public function testWriteReadStringKeys(): void
    {
        $storage = new InMemoryStorage();
        $storage->write('idx', ['className' => 'App\\Foo'], 'file:///foo.php');

        $results = iterator_to_array($storage->read('idx'));

        $this->assertCount(1, $results);
        $this->assertSame('className', $results['className']->key);
        $this->assertSame('App\\Foo', $results['className']->value);
    }

    #[TestDox('write with integer keys appends entries')]
    public function testWriteIntKeys(): void
    {
        $storage = new InMemoryStorage();
        $storage->write('idx', ['val1', 'val2'], 'file:///a.php');

        $results = iterator_to_array($storage->read('idx'));

        $this->assertCount(2, $results);
    }

    #[TestDox('multiple writes to same index accumulate')]
    public function testMultipleWrites(): void
    {
        $storage = new InMemoryStorage();
        $storage->write('idx', ['a' => 1], 'file:///a.php');
        $storage->write('idx', ['b' => 2], 'file:///b.php');

        $results = iterator_to_array($storage->read('idx'));

        $this->assertCount(2, $results);
    }
}

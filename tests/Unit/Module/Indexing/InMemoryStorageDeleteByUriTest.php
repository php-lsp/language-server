<?php

declare(strict_types=1);

namespace App\Tests\Unit\Module\Indexing;

use App\Module\Indexing\Storage\InMemoryStorage;
use App\Tests\TestCase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\TestDox;

#[Group('unit')]
final class InMemoryStorageDeleteByUriTest extends TestCase
{
    #[TestDox('deleteByUri removes entries with matching URI')]
    public function testDeleteByUriRemovesMatchingEntries(): void
    {
        $storage = new InMemoryStorage();
        $storage->write('test.key', ['a' => 'value1'], 'file:///foo.php');
        $storage->write('test.key', ['b' => 'value2'], 'file:///bar.php');

        $entries = iterator_to_array($storage->read('test.key'));
        $this->assertCount(2, $entries);

        $storage->deleteByUri('file:///foo.php');

        $entries = iterator_to_array($storage->read('test.key'));
        $this->assertCount(1, $entries);
        $this->assertSame('file:///bar.php', $entries[0]->uri);
    }

    #[TestDox('deleteByUri handles missing URI gracefully')]
    public function testDeleteByUriHandlesMissingUri(): void
    {
        $storage = new InMemoryStorage();
        $storage->write('test.key', ['a' => 'value1'], 'file:///foo.php');

        $storage->deleteByUri('file:///nonexistent.php');

        $entries = iterator_to_array($storage->read('test.key'));
        $this->assertCount(1, $entries);
    }

    #[TestDox('deleteByUri removes across multiple index keys')]
    public function testDeleteByUriRemovesAcrossKeys(): void
    {
        $storage = new InMemoryStorage();
        $storage->write('key1', ['a' => 'value1'], 'file:///foo.php');
        $storage->write('key2', ['b' => 'value2'], 'file:///foo.php');
        $storage->write('key1', ['c' => 'value3'], 'file:///bar.php');

        $storage->deleteByUri('file:///foo.php');

        $entries1 = iterator_to_array($storage->read('key1'));
        $entries2 = iterator_to_array($storage->read('key2'));

        $this->assertCount(1, $entries1);
        $this->assertCount(0, $entries2);
    }

    #[TestDox('deleteByUri clears secondary indexes')]
    public function testDeleteByUriClearsSecondaryIndexes(): void
    {
        $storage = new InMemoryStorage();

        $data1 = new \stdClass();
        $data1->name = 'Foo';
        $data2 = new \stdClass();
        $data2->name = 'Bar';

        $storage->write('test.key', ['a' => $data1], 'file:///foo.php');
        $storage->write('test.key', ['b' => $data2], 'file:///bar.php');

        $results = iterator_to_array($storage->readByField('test.key', 'name', 'Foo'));
        $this->assertCount(1, $results);

        $storage->deleteByUri('file:///foo.php');

        $results = iterator_to_array($storage->readByField('test.key', 'name', 'Foo'));
        $this->assertCount(0, $results);
    }
}

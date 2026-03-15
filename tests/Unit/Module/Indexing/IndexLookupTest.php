<?php

declare(strict_types=1);

namespace App\Tests\Unit\Module\Indexing;

use App\Module\Indexing\Indexer\ClassIndexer;
use App\Module\Indexing\IndexLookup;
use App\Module\Indexing\Storage\Entry;
use App\Module\Indexing\Storage\InMemoryStorage;
use App\Tests\TestCase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\TestDox;

#[Group('unit')]
final class IndexLookupTest extends TestCase
{
    #[TestDox('findByKey delegates to storage')]
    public function testFindByKey(): void
    {
        $storage = new InMemoryStorage();
        $storage->write(ClassIndexer::getKey(), ['App\\Foo' => 'App\\Foo'], 'file:///foo.php');

        $lookup = new IndexLookup($storage);
        $results = iterator_to_array($lookup->findByKey(ClassIndexer::class));

        $this->assertNotEmpty($results);
        $this->assertInstanceOf(Entry::class, reset($results));
    }
}

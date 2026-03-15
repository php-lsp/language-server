<?php

declare(strict_types=1);

namespace App\Tests\Unit\Module\Indexing\Storage;

use App\Module\Indexing\Storage\DebugStorageInterface;
use App\Module\Indexing\Storage\Entry;
use App\Module\Indexing\Storage\InMemoryStorage;
use App\Tests\TestCase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\TestDox;

#[Group('unit')]
final class DebugStorageTest extends TestCase
{
    private function createStorage(): InMemoryStorage
    {
        $storage = new InMemoryStorage();
        $storage->write('php.classes.fqn', [
            'App\\Foo' => 'App\\Foo',
            'App\\Bar' => 'App\\Bar',
            'App\\Controller\\HomeController' => 'App\\Controller\\HomeController',
        ], 'file:///src/Foo.php');
        $storage->write('php.functions.fqn', [
            'App\\hello' => 'App\\hello',
        ], 'file:///src/functions.php');

        return $storage;
    }

    #[TestDox('implements DebugStorageInterface')]
    public function testImplementsInterface(): void
    {
        $storage = new InMemoryStorage();
        $this->assertInstanceOf(DebugStorageInterface::class, $storage);
    }

    // --- getIndexKeys ---

    #[TestDox('getIndexKeys returns empty array for fresh storage')]
    public function testGetIndexKeysEmpty(): void
    {
        $storage = new InMemoryStorage();
        $this->assertSame([], $storage->getIndexKeys());
    }

    #[TestDox('getIndexKeys returns all written index keys')]
    public function testGetIndexKeysReturnsAll(): void
    {
        $storage = $this->createStorage();
        $keys = $storage->getIndexKeys();

        $this->assertCount(2, $keys);
        $this->assertContains('php.classes.fqn', $keys);
        $this->assertContains('php.functions.fqn', $keys);
    }

    #[TestDox('getIndexKeys does not duplicate after multiple writes to same index')]
    public function testGetIndexKeysNoDuplicates(): void
    {
        $storage = new InMemoryStorage();
        $storage->write('idx', ['a' => 1], 'file:///a.php');
        $storage->write('idx', ['b' => 2], 'file:///b.php');

        $this->assertSame(['idx'], $storage->getIndexKeys());
    }

    // --- count ---

    #[TestDox('count returns 0 for unknown index')]
    public function testCountUnknownIndex(): void
    {
        $storage = new InMemoryStorage();
        $this->assertSame(0, $storage->count('nonexistent'));
    }

    #[TestDox('count returns correct number of entries')]
    public function testCountReturnsCorrect(): void
    {
        $storage = $this->createStorage();
        $this->assertSame(3, $storage->count('php.classes.fqn'));
        $this->assertSame(1, $storage->count('php.functions.fqn'));
    }

    #[TestDox('count reflects accumulated writes')]
    public function testCountAccumulated(): void
    {
        $storage = new InMemoryStorage();
        $storage->write('idx', ['a' => 1], 'file:///a.php');
        $this->assertSame(1, $storage->count('idx'));

        $storage->write('idx', ['b' => 2], 'file:///b.php');
        $this->assertSame(2, $storage->count('idx'));
    }

    // --- find ---

    #[TestDox('find returns null for unknown index')]
    public function testFindUnknownIndex(): void
    {
        $storage = new InMemoryStorage();
        $this->assertNull($storage->find('nonexistent', 'key'));
    }

    #[TestDox('find returns null for unknown key')]
    public function testFindUnknownKey(): void
    {
        $storage = $this->createStorage();
        $this->assertNull($storage->find('php.classes.fqn', 'App\\NonExistent'));
    }

    #[TestDox('find returns entry by exact key')]
    public function testFindExactKey(): void
    {
        $storage = $this->createStorage();
        $entry = $storage->find('php.classes.fqn', 'App\\Foo');

        $this->assertInstanceOf(Entry::class, $entry);
        $this->assertSame('App\\Foo', $entry->key);
        $this->assertSame('App\\Foo', $entry->value);
        $this->assertSame('file:///src/Foo.php', $entry->uri);
    }

    #[TestDox('find is case-sensitive')]
    public function testFindCaseSensitive(): void
    {
        $storage = $this->createStorage();
        $this->assertNull($storage->find('php.classes.fqn', 'app\\foo'));
    }

    // --- search ---

    #[TestDox('search returns empty for unknown index')]
    public function testSearchUnknownIndex(): void
    {
        $storage = new InMemoryStorage();
        $results = iterator_to_array($storage->search('nonexistent', '*'));

        $this->assertSame([], $results);
    }

    #[TestDox('search matches all with wildcard')]
    public function testSearchWildcard(): void
    {
        $storage = $this->createStorage();
        $results = iterator_to_array($storage->search('php.classes.fqn', '*'));

        $this->assertCount(3, $results);
    }

    #[TestDox('search matches by prefix pattern')]
    public function testSearchPrefixPattern(): void
    {
        $storage = $this->createStorage();
        $results = iterator_to_array($storage->search('php.classes.fqn', 'App\\Controller\\*'));

        $this->assertCount(1, $results);
        $this->assertSame('App\\Controller\\HomeController', $results[0]->key);
    }

    #[TestDox('search matches by suffix pattern')]
    public function testSearchSuffixPattern(): void
    {
        $storage = $this->createStorage();
        $results = iterator_to_array($storage->search('php.classes.fqn', '*Foo'));

        $this->assertCount(1, $results);
        $this->assertSame('App\\Foo', $results[0]->key);
    }

    #[TestDox('search is case-insensitive')]
    public function testSearchCaseInsensitive(): void
    {
        $storage = $this->createStorage();
        $results = iterator_to_array($storage->search('php.classes.fqn', 'app\\foo'));

        $this->assertCount(1, $results);
    }

    #[TestDox('search returns no matches for non-matching pattern')]
    public function testSearchNoMatch(): void
    {
        $storage = $this->createStorage();
        $results = iterator_to_array($storage->search('php.classes.fqn', 'Vendor\\*'));

        $this->assertSame([], $results);
    }

    // --- stats ---

    #[TestDox('stats returns empty array for fresh storage')]
    public function testStatsEmpty(): void
    {
        $storage = new InMemoryStorage();
        $this->assertSame([], $storage->stats());
    }

    #[TestDox('stats returns correct summary for all indexes')]
    public function testStatsReturnsAll(): void
    {
        $storage = $this->createStorage();
        $stats = $storage->stats();

        $this->assertCount(2, $stats);

        $this->assertArrayHasKey('php.classes.fqn', $stats);
        $this->assertSame('php.classes.fqn', $stats['php.classes.fqn']['key']);
        $this->assertSame(3, $stats['php.classes.fqn']['count']);
        $this->assertArrayHasKey('memory', $stats['php.classes.fqn']);
        $this->assertGreaterThan(0, $stats['php.classes.fqn']['memory']);

        $this->assertArrayHasKey('php.functions.fqn', $stats);
        $this->assertSame('php.functions.fqn', $stats['php.functions.fqn']['key']);
        $this->assertSame(1, $stats['php.functions.fqn']['count']);
    }

    // --- memoryUsage ---

    #[TestDox('memoryUsage returns 0 for unknown index')]
    public function testMemoryUsageUnknownIndex(): void
    {
        $storage = new InMemoryStorage();
        $this->assertSame(0, $storage->memoryUsage('nonexistent'));
    }

    #[TestDox('memoryUsage returns positive value for populated index')]
    public function testMemoryUsagePositive(): void
    {
        $storage = $this->createStorage();
        $this->assertGreaterThan(0, $storage->memoryUsage('php.classes.fqn'));
    }

    // --- searchAll ---

    #[TestDox('searchAll finds entries across all indexes')]
    public function testSearchAllFindsAcrossIndexes(): void
    {
        $storage = $this->createStorage();
        $results = iterator_to_array($storage->searchAll('App\\*'));

        $this->assertCount(4, $results);

        $indexes = array_unique(array_column($results, 'index'));
        $this->assertCount(2, $indexes);
    }

    #[TestDox('searchAll respects limit')]
    public function testSearchAllRespectsLimit(): void
    {
        $storage = $this->createStorage();
        $results = iterator_to_array($storage->searchAll('App\\*', 2));

        $this->assertCount(2, $results);
    }

    #[TestDox('searchAll returns empty for no matches')]
    public function testSearchAllNoMatches(): void
    {
        $storage = $this->createStorage();
        $results = iterator_to_array($storage->searchAll('Vendor\\*'));

        $this->assertSame([], $results);
    }

    #[TestDox('searchAll result contains index, key, value, uri')]
    public function testSearchAllResultStructure(): void
    {
        $storage = $this->createStorage();
        $results = iterator_to_array($storage->searchAll('App\\Foo'));

        $this->assertNotEmpty($results);
        $hit = $results[0];
        $this->assertArrayHasKey('index', $hit);
        $this->assertArrayHasKey('key', $hit);
        $this->assertArrayHasKey('value', $hit);
        $this->assertArrayHasKey('uri', $hit);
    }

    // --- clearByUri ---

    #[TestDox('clearByUri removes entries with matching URI')]
    public function testClearByUri(): void
    {
        $storage = $this->createStorage();
        $storage->clearByUri('file:///src/Foo.php');

        $this->assertSame(0, $storage->count('php.classes.fqn'));
        // Other URI entries should remain
        $this->assertSame(1, $storage->count('php.functions.fqn'));
    }

    #[TestDox('clearByUri with non-existent URI does nothing')]
    public function testClearByUriNonExistent(): void
    {
        $storage = $this->createStorage();
        $storage->clearByUri('file:///nonexistent.php');

        $this->assertSame(3, $storage->count('php.classes.fqn'));
    }

    // --- getUris ---

    #[TestDox('getUris returns all unique URIs sorted')]
    public function testGetUris(): void
    {
        $storage = $this->createStorage();
        $uris = $storage->getUris();

        $this->assertCount(2, $uris);
        $this->assertContains('file:///src/Foo.php', $uris);
        $this->assertContains('file:///src/functions.php', $uris);
    }

    #[TestDox('getUris returns empty for fresh storage')]
    public function testGetUrisEmpty(): void
    {
        $storage = new InMemoryStorage();
        $this->assertSame([], $storage->getUris());
    }

    // --- findByUri ---

    #[TestDox('findByUri returns entries grouped by index')]
    public function testFindByUri(): void
    {
        $storage = $this->createStorage();
        $result = $storage->findByUri('file:///src/Foo.php');

        $this->assertArrayHasKey('php.classes.fqn', $result);
        $this->assertCount(3, $result['php.classes.fqn']);
        $this->assertArrayNotHasKey('php.functions.fqn', $result);
    }

    #[TestDox('findByUri returns empty for unknown URI')]
    public function testFindByUriUnknown(): void
    {
        $storage = $this->createStorage();
        $result = $storage->findByUri('file:///nonexistent.php');

        $this->assertSame([], $result);
    }
}

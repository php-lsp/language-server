<?php

declare(strict_types=1);

namespace App\Tests\Unit\Controller\Debug;

use App\Controller\Debug\IndexSearchController;
use App\Module\Indexing\Storage\InMemoryStorage;
use App\Tests\TestCase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\TestDox;

#[Group('unit')]
final class IndexSearchControllerTest extends TestCase
{
    private function createStorage(): InMemoryStorage
    {
        $storage = new InMemoryStorage();
        $storage->write('php.classes.fqn', [
            'App\\Foo' => 'App\\Foo',
            'App\\Bar' => 'App\\Bar',
            'App\\Controller\\Home' => 'App\\Controller\\Home',
        ], 'file:///src/Foo.php');
        $storage->write('php.functions.fqn', [
            'App\\hello' => 'App\\hello',
            'App\\world' => 'App\\world',
        ], 'file:///src/functions.php');

        return $storage;
    }

    #[TestDox('searches across all indexes when no index specified')]
    public function testSearchAllIndexes(): void
    {
        $storage = $this->createStorage();
        $controller = new IndexSearchController($storage);

        $result = $controller((object) ['pattern' => 'App\\*']);

        $this->assertArrayHasKey('php.classes.fqn', $result);
        $this->assertArrayHasKey('php.functions.fqn', $result);
        $this->assertCount(3, $result['php.classes.fqn']);
        $this->assertCount(2, $result['php.functions.fqn']);
    }

    #[TestDox('searches within specific index')]
    public function testSearchSpecificIndex(): void
    {
        $storage = $this->createStorage();
        $controller = new IndexSearchController($storage);

        $result = $controller((object) ['pattern' => 'App\\*', 'index' => 'php.classes.fqn']);

        $this->assertArrayHasKey('php.classes.fqn', $result);
        $this->assertArrayNotHasKey('php.functions.fqn', $result);
    }

    #[TestDox('returns empty when no matches')]
    public function testNoMatches(): void
    {
        $storage = $this->createStorage();
        $controller = new IndexSearchController($storage);

        $result = $controller((object) ['pattern' => 'Vendor\\*']);

        $this->assertSame([], $result);
    }

    #[TestDox('respects limit parameter')]
    public function testLimit(): void
    {
        $storage = $this->createStorage();
        $controller = new IndexSearchController($storage);

        $result = $controller((object) ['pattern' => 'App\\*', 'index' => 'php.classes.fqn', 'limit' => 2]);

        $this->assertCount(2, $result['php.classes.fqn']);
    }

    #[TestDox('pattern filters results correctly')]
    public function testPatternFiltering(): void
    {
        $storage = $this->createStorage();
        $controller = new IndexSearchController($storage);

        $result = $controller((object) ['pattern' => 'App\\Controller\\*', 'index' => 'php.classes.fqn']);

        $this->assertCount(1, $result['php.classes.fqn']);
        $this->assertSame('App\\Controller\\Home', $result['php.classes.fqn'][0]['key']);
    }

    #[TestDox('result entries contain key, value, uri')]
    public function testResultStructure(): void
    {
        $storage = $this->createStorage();
        $controller = new IndexSearchController($storage);

        $result = $controller((object) ['pattern' => 'App\\Foo', 'index' => 'php.classes.fqn']);

        $entry = $result['php.classes.fqn'][0];
        $this->assertArrayHasKey('key', $entry);
        $this->assertArrayHasKey('value', $entry);
        $this->assertArrayHasKey('uri', $entry);
    }

    #[TestDox('summarizes object values as class name')]
    public function testSummarizesObjects(): void
    {
        $storage = new InMemoryStorage();
        $storage->write('idx', ['k' => new \stdClass()], 'file:///a.php');

        $controller = new IndexSearchController($storage);
        $result = $controller((object) ['pattern' => '*', 'index' => 'idx']);

        $this->assertSame('(stdClass)', $result['idx'][0]['value']);
    }

    #[TestDox('summarizes array values with count')]
    public function testSummarizesArrays(): void
    {
        $storage = new InMemoryStorage();
        $storage->write('idx', ['k' => [1, 2, 3]], 'file:///a.php');

        $controller = new IndexSearchController($storage);
        $result = $controller((object) ['pattern' => '*', 'index' => 'idx']);

        $this->assertSame('(array[3])', $result['idx'][0]['value']);
    }

    #[TestDox('empty storage returns empty result')]
    public function testEmptyStorage(): void
    {
        $storage = new InMemoryStorage();
        $controller = new IndexSearchController($storage);

        $result = $controller((object) ['pattern' => '*']);

        $this->assertSame([], $result);
    }
}

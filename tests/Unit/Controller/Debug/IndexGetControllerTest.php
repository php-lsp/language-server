<?php

declare(strict_types=1);

namespace App\Tests\Unit\Controller\Debug;

use App\Controller\Debug\IndexGetController;
use App\Module\Indexing\Storage\InMemoryStorage;
use App\Tests\TestCase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\TestDox;

#[Group('unit')]
final class IndexGetControllerTest extends TestCase
{
    private function createStorage(): InMemoryStorage
    {
        $storage = new InMemoryStorage();
        $storage->write('php.classes.fqn', [
            'App\\Foo' => 'App\\Foo',
        ], 'file:///src/Foo.php');

        return $storage;
    }

    #[TestDox('returns error for unknown index')]
    public function testUnknownIndex(): void
    {
        $storage = new InMemoryStorage();
        $controller = new IndexGetController($storage);

        $result = $controller((object) ['index' => 'nonexistent', 'key' => 'k']);

        $this->assertArrayHasKey('error', $result);
        $this->assertStringContainsString('not found', $result['error']);
        $this->assertArrayHasKey('available', $result);
    }

    #[TestDox('returns error for unknown key')]
    public function testUnknownKey(): void
    {
        $storage = $this->createStorage();
        $controller = new IndexGetController($storage);

        $result = $controller((object) ['index' => 'php.classes.fqn', 'key' => 'App\\Missing']);

        $this->assertArrayHasKey('error', $result);
        $this->assertStringContainsString('not found', $result['error']);
    }

    #[TestDox('returns entry data for valid index and key')]
    public function testReturnsEntry(): void
    {
        $storage = $this->createStorage();
        $controller = new IndexGetController($storage);

        $result = $controller((object) ['index' => 'php.classes.fqn', 'key' => 'App\\Foo']);

        $this->assertSame('App\\Foo', $result['key']);
        $this->assertSame('App\\Foo', $result['value']);
        $this->assertSame('file:///src/Foo.php', $result['uri']);
    }

    #[TestDox('serializes object values with reflection')]
    public function testSerializesObjectValues(): void
    {
        $storage = new InMemoryStorage();
        $obj = new \stdClass();
        $obj->name = 'test';
        $obj->count = 42;
        $storage->write('idx', ['key1' => $obj], 'file:///a.php');

        $controller = new IndexGetController($storage);
        $result = $controller((object) ['index' => 'idx', 'key' => 'key1']);

        $this->assertSame('key1', $result['key']);
        $this->assertIsArray($result['value']);
        $this->assertSame('stdClass', $result['value']['__class']);
        $this->assertSame('test', $result['value']['name']);
        $this->assertSame(42, $result['value']['count']);
    }

    #[TestDox('serializes array values recursively')]
    public function testSerializesArrayValues(): void
    {
        $storage = new InMemoryStorage();
        $storage->write('idx', ['key1' => ['a', 'b', 'c']], 'file:///a.php');

        $controller = new IndexGetController($storage);
        $result = $controller((object) ['index' => 'idx', 'key' => 'key1']);

        $this->assertSame(['a', 'b', 'c'], $result['value']);
    }

    #[TestDox('serializes scalar values directly')]
    public function testSerializesScalarValues(): void
    {
        $storage = new InMemoryStorage();
        $storage->write('idx', ['key1' => 42], 'file:///a.php');

        $controller = new IndexGetController($storage);
        $result = $controller((object) ['index' => 'idx', 'key' => 'key1']);

        $this->assertSame(42, $result['value']);
    }

    #[TestDox('returns available indexes on error')]
    public function testReturnsAvailableIndexes(): void
    {
        $storage = $this->createStorage();
        $controller = new IndexGetController($storage);

        $result = $controller((object) ['index' => 'nonexistent', 'key' => 'k']);

        $this->assertContains('php.classes.fqn', $result['available']);
    }
}

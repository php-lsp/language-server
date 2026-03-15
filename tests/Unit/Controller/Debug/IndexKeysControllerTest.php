<?php

declare(strict_types=1);

namespace App\Tests\Unit\Controller\Debug;

use App\Controller\Debug\IndexKeysController;
use App\Module\Indexing\Storage\InMemoryStorage;
use App\Tests\TestCase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\TestDox;

#[Group('unit')]
final class IndexKeysControllerTest extends TestCase
{
    private function createStorage(): InMemoryStorage
    {
        $storage = new InMemoryStorage();
        $storage->write('php.classes.fqn', [
            'App\\Foo' => 'App\\Foo',
            'App\\Bar' => 'App\\Bar',
            'App\\Baz' => 'App\\Baz',
            'Vendor\\Lib' => 'Vendor\\Lib',
        ], 'file:///src/Foo.php');

        return $storage;
    }

    #[TestDox('returns error for unknown index')]
    public function testUnknownIndex(): void
    {
        $storage = new InMemoryStorage();
        $controller = new IndexKeysController($storage);

        $result = $controller((object) ['index' => 'nonexistent']);

        $this->assertArrayHasKey('error', $result);
        $this->assertStringContainsString('not found', $result['error']);
        $this->assertArrayHasKey('available', $result);
    }

    #[TestDox('returns all keys for known index')]
    public function testReturnsAllKeys(): void
    {
        $storage = $this->createStorage();
        $controller = new IndexKeysController($storage);

        $result = $controller((object) ['index' => 'php.classes.fqn']);

        $this->assertSame('php.classes.fqn', $result['index']);
        $this->assertSame(4, $result['total']);
        $this->assertCount(4, $result['keys']);
        $this->assertContains('App\\Foo', $result['keys']);
        $this->assertContains('Vendor\\Lib', $result['keys']);
    }

    #[TestDox('filters keys by pattern')]
    public function testPatternFilter(): void
    {
        $storage = $this->createStorage();
        $controller = new IndexKeysController($storage);

        $result = $controller((object) ['index' => 'php.classes.fqn', 'pattern' => 'App\\*']);

        $this->assertSame(3, $result['total']);
        $this->assertCount(3, $result['keys']);
        $this->assertNotContains('Vendor\\Lib', $result['keys']);
    }

    #[TestDox('respects limit parameter')]
    public function testLimit(): void
    {
        $storage = $this->createStorage();
        $controller = new IndexKeysController($storage);

        $result = $controller((object) ['index' => 'php.classes.fqn', 'limit' => 2]);

        $this->assertSame(4, $result['total']);
        $this->assertCount(2, $result['keys']);
        $this->assertSame(2, $result['limit']);
    }

    #[TestDox('respects offset parameter')]
    public function testOffset(): void
    {
        $storage = $this->createStorage();
        $controller = new IndexKeysController($storage);

        $result = $controller((object) ['index' => 'php.classes.fqn', 'offset' => 2, 'limit' => 2]);

        $this->assertSame(4, $result['total']);
        $this->assertCount(2, $result['keys']);
        $this->assertSame(2, $result['offset']);
    }

    #[TestDox('defaults to limit 100 and offset 0')]
    public function testDefaults(): void
    {
        $storage = $this->createStorage();
        $controller = new IndexKeysController($storage);

        $result = $controller((object) ['index' => 'php.classes.fqn']);

        $this->assertSame(100, $result['limit']);
        $this->assertSame(0, $result['offset']);
    }

    #[TestDox('returns available indexes in error response')]
    public function testAvailableInError(): void
    {
        $storage = $this->createStorage();
        $controller = new IndexKeysController($storage);

        $result = $controller((object) ['index' => 'nonexistent']);

        $this->assertContains('php.classes.fqn', $result['available']);
    }

    #[TestDox('pattern and pagination combine correctly')]
    public function testPatternWithPagination(): void
    {
        $storage = $this->createStorage();
        $controller = new IndexKeysController($storage);

        $result = $controller((object) ['index' => 'php.classes.fqn', 'pattern' => 'App\\*', 'limit' => 1, 'offset' => 1]);

        $this->assertSame(3, $result['total']);
        $this->assertCount(1, $result['keys']);
        $this->assertSame(1, $result['offset']);
    }

    #[TestDox('empty pattern returns all keys')]
    public function testEmptyPattern(): void
    {
        $storage = $this->createStorage();
        $controller = new IndexKeysController($storage);

        $result = $controller((object) ['index' => 'php.classes.fqn', 'pattern' => '']);

        $this->assertSame(4, $result['total']);
    }
}

<?php

declare(strict_types=1);

namespace App\Tests\Unit\Controller\Debug;

use App\Controller\Debug\IndexListController;
use App\Module\Indexing\Storage\InMemoryStorage;
use App\Tests\TestCase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\TestDox;

#[Group('unit')]
final class IndexListControllerTest extends TestCase
{
    #[TestDox('returns empty array for empty storage')]
    public function testEmptyStorage(): void
    {
        $storage = new InMemoryStorage();
        $controller = new IndexListController($storage);

        $result = $controller();

        $this->assertSame([], $result);
    }

    #[TestDox('returns stats for all indexes')]
    public function testReturnsStats(): void
    {
        $storage = new InMemoryStorage();
        $storage->write('php.classes.fqn', ['A' => 'A', 'B' => 'B'], 'file:///a.php');
        $storage->write('php.functions.fqn', ['fn' => 'fn'], 'file:///b.php');

        $controller = new IndexListController($storage);
        $result = $controller();

        $this->assertCount(2, $result);
        $this->assertSame(2, $result['php.classes.fqn']['count']);
        $this->assertSame(1, $result['php.functions.fqn']['count']);
    }
}

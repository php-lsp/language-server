<?php

declare(strict_types=1);

namespace App\Tests\Unit\Module\Indexing\Indexer;

use App\Module\Indexing\Data\PropertyData;
use App\Module\Indexing\Data\Visibility;
use App\Module\Indexing\Indexer\PropertyIndexer;
use App\Tests\TestCase;
use App\Tests\Unit\Module\Indexing\IndexerTestHelper;
use PHPUnit\Framework\Attributes\Group;

#[Group('unit')]
final class PropertyIndexerTest extends TestCase
{
    public function testGetKey(): void
    {
        $this->assertSame('php.properties.fqn', PropertyIndexer::getKey());
    }

    public function testIndexPublicProperty(): void
    {
        $code = '<?php class Foo { public string $name; }';
        $results = IndexerTestHelper::runIndexer(new PropertyIndexer($this->createMock(\App\Module\PsiFile\InMemoryPsiFileManager::class)), $code);

        $this->assertArrayHasKey('Foo', $results);
        $this->assertCount(1, $results['Foo']);
        $this->assertInstanceOf(PropertyData::class, $results['Foo'][0]);
        $this->assertSame('name', $results['Foo'][0]->name);
        $this->assertSame('Foo', $results['Foo'][0]->className);
        $this->assertSame(Visibility::Public, $results['Foo'][0]->visibility);
        $this->assertSame('string', $results['Foo'][0]->type);
        $this->assertFalse($results['Foo'][0]->isStatic);
        $this->assertFalse($results['Foo'][0]->isReadonly);
        $this->assertFalse($results['Foo'][0]->isPromoted);
    }

    public function testIndexPrivateProperty(): void
    {
        $code = '<?php class Foo { private int $id; }';
        $results = IndexerTestHelper::runIndexer(new PropertyIndexer($this->createMock(\App\Module\PsiFile\InMemoryPsiFileManager::class)), $code);

        $this->assertSame(Visibility::Private, $results['Foo'][0]->visibility);
    }

    public function testIndexProtectedProperty(): void
    {
        $code = '<?php class Foo { protected mixed $data; }';
        $results = IndexerTestHelper::runIndexer(new PropertyIndexer($this->createMock(\App\Module\PsiFile\InMemoryPsiFileManager::class)), $code);

        $this->assertSame(Visibility::Protected, $results['Foo'][0]->visibility);
    }

    public function testIndexStaticProperty(): void
    {
        $code = '<?php class Foo { public static int $count = 0; }';
        $results = IndexerTestHelper::runIndexer(new PropertyIndexer($this->createMock(\App\Module\PsiFile\InMemoryPsiFileManager::class)), $code);

        $this->assertTrue($results['Foo'][0]->isStatic);
    }

    public function testIndexReadonlyProperty(): void
    {
        $code = '<?php class Foo { public readonly string $name; }';
        $results = IndexerTestHelper::runIndexer(new PropertyIndexer($this->createMock(\App\Module\PsiFile\InMemoryPsiFileManager::class)), $code);

        $this->assertTrue($results['Foo'][0]->isReadonly);
    }

    public function testIndexPromotedProperty(): void
    {
        $code = '<?php class Foo { public function __construct(public readonly string $name, private int $age) {} }';
        $results = IndexerTestHelper::runIndexer(new PropertyIndexer($this->createMock(\App\Module\PsiFile\InMemoryPsiFileManager::class)), $code);

        $this->assertCount(2, $results['Foo']);

        $name = $results['Foo'][0];
        $this->assertSame('name', $name->name);
        $this->assertTrue($name->isPromoted);
        $this->assertTrue($name->isReadonly);
        $this->assertSame(Visibility::Public, $name->visibility);

        $age = $results['Foo'][1];
        $this->assertSame('age', $age->name);
        $this->assertTrue($age->isPromoted);
        $this->assertSame(Visibility::Private, $age->visibility);
    }

    public function testNonPromotedConstructorParamsSkipped(): void
    {
        $code = '<?php class Foo { public function __construct(string $temp) {} }';
        $results = IndexerTestHelper::runIndexer(new PropertyIndexer($this->createMock(\App\Module\PsiFile\InMemoryPsiFileManager::class)), $code);

        $this->assertCount(0, $results);
    }

    public function testIndexTraitProperties(): void
    {
        $code = '<?php trait Foo { public string $name; }';
        $results = IndexerTestHelper::runIndexer(new PropertyIndexer($this->createMock(\App\Module\PsiFile\InMemoryPsiFileManager::class)), $code);

        $this->assertArrayHasKey('Foo', $results);
        $this->assertSame('name', $results['Foo'][0]->name);
    }

    public function testIndexMultipleProperties(): void
    {
        $code = '<?php class Foo { public string $a; private int $b; protected bool $c; }';
        $results = IndexerTestHelper::runIndexer(new PropertyIndexer($this->createMock(\App\Module\PsiFile\InMemoryPsiFileManager::class)), $code);

        $this->assertCount(3, $results['Foo']);
    }

    public function testEmptyClassSkipped(): void
    {
        $code = '<?php class Foo {}';
        $results = IndexerTestHelper::runIndexer(new PropertyIndexer($this->createMock(\App\Module\PsiFile\InMemoryPsiFileManager::class)), $code);

        $this->assertCount(0, $results);
    }
}

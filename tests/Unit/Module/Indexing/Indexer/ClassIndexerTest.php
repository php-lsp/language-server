<?php

declare(strict_types=1);

namespace App\Tests\Unit\Module\Indexing\Indexer;

use App\Module\Indexing\Data\ClassData;
use App\Module\Indexing\Indexer\ClassIndexer;
use App\Tests\TestCase;
use App\Tests\Unit\Module\Indexing\IndexerTestHelper;
use PHPUnit\Framework\Attributes\Group;

#[Group('unit')]
final class ClassIndexerTest extends TestCase
{
    public function testGetKey(): void
    {
        $this->assertSame('php.classes.fqn', ClassIndexer::getKey());
    }

    public function testIndexSimpleClass(): void
    {
        $results = IndexerTestHelper::runIndexer(new ClassIndexer($this->createMock(\App\Module\PsiFile\InMemoryPsiFileManager::class)), '<?php class Foo {}');

        $this->assertCount(1, $results);
        $this->assertArrayHasKey('Foo', $results);
        $this->assertInstanceOf(ClassData::class, $results['Foo']);
        $this->assertSame('Foo', $results['Foo']->fqn);
        $this->assertFalse($results['Foo']->isAbstract);
        $this->assertFalse($results['Foo']->isFinal);
        $this->assertFalse($results['Foo']->isReadonly);
        $this->assertNull($results['Foo']->extends);
        $this->assertSame([], $results['Foo']->implements);
    }

    public function testIndexNamespacedClass(): void
    {
        $code = '<?php namespace App\\Models; class User {}';
        $results = IndexerTestHelper::runIndexer(new ClassIndexer($this->createMock(\App\Module\PsiFile\InMemoryPsiFileManager::class)), $code);

        $this->assertArrayHasKey('App\\Models\\User', $results);
        $this->assertSame('App\\Models\\User', $results['App\\Models\\User']->fqn);
    }

    public function testIndexAbstractClass(): void
    {
        $code = '<?php abstract class Base {}';
        $results = IndexerTestHelper::runIndexer(new ClassIndexer($this->createMock(\App\Module\PsiFile\InMemoryPsiFileManager::class)), $code);

        $this->assertTrue($results['Base']->isAbstract);
        $this->assertFalse($results['Base']->isFinal);
    }

    public function testIndexFinalClass(): void
    {
        $code = '<?php final class Sealed {}';
        $results = IndexerTestHelper::runIndexer(new ClassIndexer($this->createMock(\App\Module\PsiFile\InMemoryPsiFileManager::class)), $code);

        $this->assertTrue($results['Sealed']->isFinal);
        $this->assertFalse($results['Sealed']->isAbstract);
    }

    public function testIndexReadonlyClass(): void
    {
        $code = '<?php readonly class Immutable {}';
        $results = IndexerTestHelper::runIndexer(new ClassIndexer($this->createMock(\App\Module\PsiFile\InMemoryPsiFileManager::class)), $code);

        $this->assertTrue($results['Immutable']->isReadonly);
    }

    public function testIndexClassWithExtends(): void
    {
        $code = '<?php class Child extends Parent_ {}';
        $results = IndexerTestHelper::runIndexer(new ClassIndexer($this->createMock(\App\Module\PsiFile\InMemoryPsiFileManager::class)), $code);

        $this->assertSame('Parent_', $results['Child']->extends);
    }

    public function testIndexClassWithImplements(): void
    {
        $code = '<?php class Foo implements Bar, Baz {}';
        $results = IndexerTestHelper::runIndexer(new ClassIndexer($this->createMock(\App\Module\PsiFile\InMemoryPsiFileManager::class)), $code);

        $this->assertSame(['Bar', 'Baz'], $results['Foo']->implements);
    }

    public function testIndexClassWithExtendsAndImplements(): void
    {
        $code = '<?php class Child extends Base implements Iface1, Iface2 {}';
        $results = IndexerTestHelper::runIndexer(new ClassIndexer($this->createMock(\App\Module\PsiFile\InMemoryPsiFileManager::class)), $code);

        $this->assertSame('Base', $results['Child']->extends);
        $this->assertSame(['Iface1', 'Iface2'], $results['Child']->implements);
    }

    public function testIndexMultipleClasses(): void
    {
        $code = '<?php class A {} class B {} class C {}';
        $results = IndexerTestHelper::runIndexer(new ClassIndexer($this->createMock(\App\Module\PsiFile\InMemoryPsiFileManager::class)), $code);

        $this->assertCount(3, $results);
        $this->assertArrayHasKey('A', $results);
        $this->assertArrayHasKey('B', $results);
        $this->assertArrayHasKey('C', $results);
    }

    public function testIndexAnonymousClassSkipped(): void
    {
        $code = '<?php $a = new class {};';
        $results = IndexerTestHelper::runIndexer(new ClassIndexer($this->createMock(\App\Module\PsiFile\InMemoryPsiFileManager::class)), $code);

        $this->assertCount(0, $results);
    }

    public function testPositionsAreRecorded(): void
    {
        $code = '<?php class Foo {}';
        $results = IndexerTestHelper::runIndexer(new ClassIndexer($this->createMock(\App\Module\PsiFile\InMemoryPsiFileManager::class)), $code);

        $this->assertGreaterThanOrEqual(0, $results['Foo']->startPosition);
        $this->assertGreaterThan($results['Foo']->startPosition, $results['Foo']->endPosition);
    }
}

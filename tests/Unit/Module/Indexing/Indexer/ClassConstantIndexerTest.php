<?php

declare(strict_types=1);

namespace App\Tests\Unit\Module\Indexing\Indexer;

use App\Module\Indexing\Data\ConstantData;
use App\Module\Indexing\Data\Visibility;
use App\Module\Indexing\Indexer\ClassConstantIndexer;
use App\Tests\TestCase;
use App\Tests\Unit\Module\Indexing\IndexerTestHelper;
use PHPUnit\Framework\Attributes\Group;

#[Group('unit')]
final class ClassConstantIndexerTest extends TestCase
{
    public function testGetKey(): void
    {
        $this->assertSame('php.classConstants.fqn', ClassConstantIndexer::getKey());
    }

    public function testIndexClassConstant(): void
    {
        $code = '<?php class Foo { const BAR = 1; }';
        $results = IndexerTestHelper::runIndexer(new ClassConstantIndexer($this->createMock(\App\Module\PsiFile\InMemoryPsiFileManager::class)), $code);

        $this->assertArrayHasKey('Foo', $results);
        $this->assertCount(1, $results['Foo']);
        $this->assertInstanceOf(ConstantData::class, $results['Foo'][0]);
        $this->assertSame('BAR', $results['Foo'][0]->name);
        $this->assertSame('Foo', $results['Foo'][0]->className);
        $this->assertSame(Visibility::Public, $results['Foo'][0]->visibility);
    }

    public function testIndexPrivateConstant(): void
    {
        $code = '<?php class Foo { private const SECRET = "s"; }';
        $results = IndexerTestHelper::runIndexer(new ClassConstantIndexer($this->createMock(\App\Module\PsiFile\InMemoryPsiFileManager::class)), $code);

        $this->assertSame(Visibility::Private, $results['Foo'][0]->visibility);
    }

    public function testIndexProtectedConstant(): void
    {
        $code = '<?php class Foo { protected const INTERNAL = true; }';
        $results = IndexerTestHelper::runIndexer(new ClassConstantIndexer($this->createMock(\App\Module\PsiFile\InMemoryPsiFileManager::class)), $code);

        $this->assertSame(Visibility::Protected, $results['Foo'][0]->visibility);
    }

    public function testIndexTypedConstant(): void
    {
        $code = '<?php class Foo { public const string NAME = "foo"; }';
        $results = IndexerTestHelper::runIndexer(new ClassConstantIndexer($this->createMock(\App\Module\PsiFile\InMemoryPsiFileManager::class)), $code);

        $this->assertSame('string', $results['Foo'][0]->type);
    }

    public function testIndexInterfaceConstant(): void
    {
        $code = '<?php interface Foo { const BAR = 1; }';
        $results = IndexerTestHelper::runIndexer(new ClassConstantIndexer($this->createMock(\App\Module\PsiFile\InMemoryPsiFileManager::class)), $code);

        $this->assertArrayHasKey('Foo', $results);
        $this->assertSame('BAR', $results['Foo'][0]->name);
    }

    public function testIndexEnumConstant(): void
    {
        $code = '<?php enum Foo { const BAR = 1; }';
        $results = IndexerTestHelper::runIndexer(new ClassConstantIndexer($this->createMock(\App\Module\PsiFile\InMemoryPsiFileManager::class)), $code);

        $this->assertArrayHasKey('Foo', $results);
    }

    public function testIndexTraitConstant(): void
    {
        $code = '<?php trait Foo { const BAR = 1; }';
        $results = IndexerTestHelper::runIndexer(new ClassConstantIndexer($this->createMock(\App\Module\PsiFile\InMemoryPsiFileManager::class)), $code);

        $this->assertArrayHasKey('Foo', $results);
    }

    public function testIndexMultipleConstants(): void
    {
        $code = '<?php class Foo { const A = 1; const B = 2; const C = 3; }';
        $results = IndexerTestHelper::runIndexer(new ClassConstantIndexer($this->createMock(\App\Module\PsiFile\InMemoryPsiFileManager::class)), $code);

        $this->assertCount(3, $results['Foo']);
    }

    public function testEmptyClassSkipped(): void
    {
        $code = '<?php class Foo {}';
        $results = IndexerTestHelper::runIndexer(new ClassConstantIndexer($this->createMock(\App\Module\PsiFile\InMemoryPsiFileManager::class)), $code);

        $this->assertCount(0, $results);
    }

    public function testNamespacedClassConstants(): void
    {
        $code = '<?php namespace App; class Foo { const BAR = 1; }';
        $results = IndexerTestHelper::runIndexer(new ClassConstantIndexer($this->createMock(\App\Module\PsiFile\InMemoryPsiFileManager::class)), $code);

        $this->assertArrayHasKey('App\\Foo', $results);
    }
}

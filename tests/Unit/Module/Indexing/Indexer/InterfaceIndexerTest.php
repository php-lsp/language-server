<?php

declare(strict_types=1);

namespace App\Tests\Unit\Module\Indexing\Indexer;

use App\Module\Indexing\Data\InterfaceData;
use App\Module\Indexing\Indexer\InterfaceIndexer;
use App\Tests\TestCase;
use App\Tests\Unit\Module\Indexing\IndexerTestHelper;
use PHPUnit\Framework\Attributes\Group;

#[Group('unit')]
final class InterfaceIndexerTest extends TestCase
{
    public function testGetKey(): void
    {
        $this->assertSame('php.interfaces.fqn', InterfaceIndexer::getKey());
    }

    public function testIndexSimpleInterface(): void
    {
        $code = '<?php interface Foo {}';
        $results = IndexerTestHelper::runIndexer(new InterfaceIndexer($this->createMock(\App\Module\PsiFile\InMemoryPsiFileManager::class)), $code);

        $this->assertCount(1, $results);
        $this->assertArrayHasKey('Foo', $results);
        $this->assertInstanceOf(InterfaceData::class, $results['Foo']);
        $this->assertSame('Foo', $results['Foo']->fqn);
        $this->assertSame([], $results['Foo']->extends);
    }

    public function testIndexNamespacedInterface(): void
    {
        $code = '<?php namespace App\\Contracts; interface Repository {}';
        $results = IndexerTestHelper::runIndexer(new InterfaceIndexer($this->createMock(\App\Module\PsiFile\InMemoryPsiFileManager::class)), $code);

        $this->assertArrayHasKey('App\\Contracts\\Repository', $results);
    }

    public function testIndexInterfaceWithExtends(): void
    {
        $code = '<?php interface Child extends Parent1, Parent2 {}';
        $results = IndexerTestHelper::runIndexer(new InterfaceIndexer($this->createMock(\App\Module\PsiFile\InMemoryPsiFileManager::class)), $code);

        $this->assertSame(['Parent1', 'Parent2'], $results['Child']->extends);
    }

    public function testIndexMultipleInterfaces(): void
    {
        $code = '<?php interface A {} interface B {} interface C {}';
        $results = IndexerTestHelper::runIndexer(new InterfaceIndexer($this->createMock(\App\Module\PsiFile\InMemoryPsiFileManager::class)), $code);

        $this->assertCount(3, $results);
    }

    public function testPositionsAreRecorded(): void
    {
        $code = '<?php interface Foo {}';
        $results = IndexerTestHelper::runIndexer(new InterfaceIndexer($this->createMock(\App\Module\PsiFile\InMemoryPsiFileManager::class)), $code);

        $this->assertGreaterThanOrEqual(0, $results['Foo']->startPosition);
        $this->assertGreaterThan($results['Foo']->startPosition, $results['Foo']->endPosition);
    }
}

<?php

declare(strict_types=1);

namespace App\Tests\Unit\Module\Indexing\Indexer;

use App\Module\Indexing\Data\TraitData;
use App\Module\Indexing\Indexer\TraitIndexer;
use App\Tests\TestCase;
use App\Tests\Unit\Module\Indexing\IndexerTestHelper;
use PHPUnit\Framework\Attributes\Group;

#[Group('unit')]
final class TraitIndexerTest extends TestCase
{
    public function testGetKey(): void
    {
        $this->assertSame('php.traits.fqn', TraitIndexer::getKey());
    }

    public function testIndexSimpleTrait(): void
    {
        $code = '<?php trait Foo {}';
        $results = IndexerTestHelper::runIndexer(new TraitIndexer($this->createMock(\App\Module\PsiFile\InMemoryPsiFileManager::class)), $code);

        $this->assertCount(1, $results);
        $this->assertArrayHasKey('Foo', $results);
        $this->assertInstanceOf(TraitData::class, $results['Foo']);
        $this->assertSame('Foo', $results['Foo']->fqn);
    }

    public function testIndexNamespacedTrait(): void
    {
        $code = '<?php namespace App\\Concerns; trait HasTimestamps {}';
        $results = IndexerTestHelper::runIndexer(new TraitIndexer($this->createMock(\App\Module\PsiFile\InMemoryPsiFileManager::class)), $code);

        $this->assertArrayHasKey('App\\Concerns\\HasTimestamps', $results);
    }

    public function testIndexMultipleTraits(): void
    {
        $code = '<?php trait A {} trait B {}';
        $results = IndexerTestHelper::runIndexer(new TraitIndexer($this->createMock(\App\Module\PsiFile\InMemoryPsiFileManager::class)), $code);

        $this->assertCount(2, $results);
    }

    public function testPositionsAreRecorded(): void
    {
        $code = '<?php trait Foo {}';
        $results = IndexerTestHelper::runIndexer(new TraitIndexer($this->createMock(\App\Module\PsiFile\InMemoryPsiFileManager::class)), $code);

        $this->assertGreaterThanOrEqual(0, $results['Foo']->startPosition);
        $this->assertGreaterThan($results['Foo']->startPosition, $results['Foo']->endPosition);
    }
}

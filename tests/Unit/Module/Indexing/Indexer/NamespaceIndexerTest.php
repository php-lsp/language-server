<?php

declare(strict_types=1);

namespace App\Tests\Unit\Module\Indexing\Indexer;

use App\Module\Indexing\Data\NamespaceData;
use App\Module\Indexing\Indexer\NamespaceIndexer;
use App\Tests\TestCase;
use App\Tests\Unit\Module\Indexing\IndexerTestHelper;
use PHPUnit\Framework\Attributes\Group;

#[Group('unit')]
final class NamespaceIndexerTest extends TestCase
{
    public function testGetKey(): void
    {
        $this->assertSame('php.namespaces.fqn', NamespaceIndexer::getKey());
    }

    public function testIndexNamespace(): void
    {
        $code = '<?php namespace App\\Models;';
        $results = IndexerTestHelper::runIndexer(new NamespaceIndexer($this->createMock(\App\Module\PsiFile\InMemoryPsiFileManager::class)), $code);

        $this->assertCount(1, $results);
        $this->assertArrayHasKey('App\\Models', $results);
        $this->assertInstanceOf(NamespaceData::class, $results['App\\Models']);
        $this->assertSame('App\\Models', $results['App\\Models']->fqn);
    }

    public function testIndexBracedNamespace(): void
    {
        $code = '<?php namespace App\\Services { class Foo {} }';
        $results = IndexerTestHelper::runIndexer(new NamespaceIndexer($this->createMock(\App\Module\PsiFile\InMemoryPsiFileManager::class)), $code);

        $this->assertArrayHasKey('App\\Services', $results);
    }

    public function testGlobalNamespaceSkipped(): void
    {
        $code = '<?php class Foo {}';
        $results = IndexerTestHelper::runIndexer(new NamespaceIndexer($this->createMock(\App\Module\PsiFile\InMemoryPsiFileManager::class)), $code);

        $this->assertCount(0, $results);
    }

    public function testPositionsAreRecorded(): void
    {
        $code = '<?php namespace Foo;';
        $results = IndexerTestHelper::runIndexer(new NamespaceIndexer($this->createMock(\App\Module\PsiFile\InMemoryPsiFileManager::class)), $code);

        $this->assertGreaterThanOrEqual(0, $results['Foo']->startPosition);
        $this->assertGreaterThan($results['Foo']->startPosition, $results['Foo']->endPosition);
    }
}

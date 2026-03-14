<?php

declare(strict_types=1);

namespace App\Tests\Unit\Module\Indexing\Indexer;

use App\Module\Indexing\Data\ConstantData;
use App\Module\Indexing\Indexer\GlobalConstantIndexer;
use App\Tests\TestCase;
use App\Tests\Unit\Module\Indexing\IndexerTestHelper;
use PHPUnit\Framework\Attributes\Group;

#[Group('unit')]
final class GlobalConstantIndexerTest extends TestCase
{
    public function testGetKey(): void
    {
        $this->assertSame('php.constants.fqn', GlobalConstantIndexer::getKey());
    }

    public function testIndexGlobalConstant(): void
    {
        $code = '<?php const FOO = "bar";';
        $results = IndexerTestHelper::runIndexer(new GlobalConstantIndexer($this->createMock(\App\Module\PsiFile\InMemoryPsiFileManager::class)), $code);

        $this->assertCount(1, $results);
        $this->assertArrayHasKey('FOO', $results);
        $this->assertInstanceOf(ConstantData::class, $results['FOO']);
        $this->assertSame('FOO', $results['FOO']->name);
        $this->assertNull($results['FOO']->className);
        $this->assertNull($results['FOO']->type);
        $this->assertNull($results['FOO']->visibility);
    }

    public function testIndexNamespacedConstant(): void
    {
        $code = '<?php namespace App; const VERSION = "1.0";';
        $results = IndexerTestHelper::runIndexer(new GlobalConstantIndexer($this->createMock(\App\Module\PsiFile\InMemoryPsiFileManager::class)), $code);

        $this->assertArrayHasKey('App\\VERSION', $results);
    }

    public function testIndexMultipleConstants(): void
    {
        $code = '<?php const A = 1; const B = 2; const C = 3;';
        $results = IndexerTestHelper::runIndexer(new GlobalConstantIndexer($this->createMock(\App\Module\PsiFile\InMemoryPsiFileManager::class)), $code);

        $this->assertCount(3, $results);
    }

    public function testPositionsAreRecorded(): void
    {
        $code = '<?php const FOO = 1;';
        $results = IndexerTestHelper::runIndexer(new GlobalConstantIndexer($this->createMock(\App\Module\PsiFile\InMemoryPsiFileManager::class)), $code);

        $this->assertGreaterThanOrEqual(0, $results['FOO']->startPosition);
        $this->assertGreaterThan($results['FOO']->startPosition, $results['FOO']->endPosition);
    }

    public function testNoConstantsReturnsEmpty(): void
    {
        $code = '<?php echo "hello";';
        $results = IndexerTestHelper::runIndexer(new GlobalConstantIndexer($this->createMock(\App\Module\PsiFile\InMemoryPsiFileManager::class)), $code);

        $this->assertCount(0, $results);
    }
}

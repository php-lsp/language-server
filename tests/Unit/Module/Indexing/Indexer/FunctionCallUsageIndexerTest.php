<?php

declare(strict_types=1);

namespace App\Tests\Unit\Module\Indexing\Indexer;

use App\Module\Indexing\Indexer\FunctionCallUsageIndexer;
use App\Tests\Support\IndexerTestHelper;
use App\Tests\Support\PsiFileFactory;
use App\Tests\TestCase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\TestDox;

#[Group('unit')]
final class FunctionCallUsageIndexerTest extends TestCase
{
    #[TestDox('returns correct key')]
    public function testGetKey(): void
    {
        $this->assertSame('php.functionCallUsages', FunctionCallUsageIndexer::getKey());
    }

    #[TestDox('indexes function calls')]
    public function testIndexesFunctionCalls(): void
    {
        $file = PsiFileFactory::fromCode('<?php myFunc(); otherFunc();');
        $result = IndexerTestHelper::indexInternal(FunctionCallUsageIndexer::class, $file);

        $this->assertCount(2, $result);
        $this->assertSame('myFunc', $result[0][0]);
        $this->assertSame('otherFunc', $result[1][0]);
    }

    #[TestDox('indexes fully qualified function calls')]
    public function testIndexesFullyQualifiedCalls(): void
    {
        $file = PsiFileFactory::fromCode('<?php \strlen("test");');
        $result = IndexerTestHelper::indexInternal(FunctionCallUsageIndexer::class, $file);

        $this->assertNotEmpty($result);
        $this->assertSame('strlen', $result[0][0]);
    }

    #[TestDox('returns empty when no function calls')]
    public function testReturnsEmptyWhenNoCalls(): void
    {
        $file = PsiFileFactory::fromCode('<?php $x = 1;');
        $result = IndexerTestHelper::indexInternal(FunctionCallUsageIndexer::class, $file);

        $this->assertEmpty($result);
    }

    #[TestDox('stores file positions')]
    public function testStoresFilePositions(): void
    {
        $file = PsiFileFactory::fromCode('<?php myFunc();');
        $result = IndexerTestHelper::indexInternal(FunctionCallUsageIndexer::class, $file);

        $this->assertIsInt($result[0][1]);
        $this->assertGreaterThan(0, $result[0][1]);
    }
}

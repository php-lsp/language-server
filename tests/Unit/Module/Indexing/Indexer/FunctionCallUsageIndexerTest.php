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
        $values = array_values($result);
        $this->assertSame('myFunc', $values[0][0]);
        $this->assertSame('otherFunc', $values[1][0]);
        $keys = array_keys($result);
        $this->assertStringContainsString('myFunc@', $keys[0]);
        $this->assertStringContainsString('otherFunc@', $keys[1]);
    }

    #[TestDox('indexes fully qualified function calls')]
    public function testIndexesFullyQualifiedCalls(): void
    {
        $file = PsiFileFactory::fromCode('<?php \strlen("test");');
        $result = IndexerTestHelper::indexInternal(FunctionCallUsageIndexer::class, $file);

        $this->assertNotEmpty($result);
        $values = array_values($result);
        $this->assertSame('strlen', $values[0][0]);
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

        $values = array_values($result);
        $this->assertIsInt($values[0][1]);
        $this->assertGreaterThan(0, $values[0][1]);
    }
}

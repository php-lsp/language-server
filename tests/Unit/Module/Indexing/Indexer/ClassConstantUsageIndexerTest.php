<?php

declare(strict_types=1);

namespace App\Tests\Unit\Module\Indexing\Indexer;

use App\Module\Indexing\Indexer\ClassConstantUsageIndexer;
use App\Tests\Support\IndexerTestHelper;
use App\Tests\Support\PsiFileFactory;
use App\Tests\TestCase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\TestDox;

#[Group('unit')]
final class ClassConstantUsageIndexerTest extends TestCase
{
    #[TestDox('returns correct key')]
    public function testGetKey(): void
    {
        $this->assertSame('php.classConstantUsages', ClassConstantUsageIndexer::getKey());
    }

    #[TestDox('indexes class constant fetch')]
    public function testIndexesClassConstantFetch(): void
    {
        $file = PsiFileFactory::fromCode('<?php \Foo::BAR;');
        $result = IndexerTestHelper::indexInternal(ClassConstantUsageIndexer::class, $file);

        $this->assertNotEmpty($result);
        $this->assertSame('Foo', $result[0][0]);
        $this->assertSame('BAR', $result[0][1]);
    }

    #[TestDox('indexes class constant in expression')]
    public function testIndexesClassConstantInExpression(): void
    {
        $file = PsiFileFactory::fromCode('<?php $x = \Foo::BAR + \Baz::QUX;');
        $result = IndexerTestHelper::indexInternal(ClassConstantUsageIndexer::class, $file);

        $this->assertCount(2, $result);
        $this->assertSame('Foo', $result[0][0]);
        $this->assertSame('BAR', $result[0][1]);
        $this->assertSame('Baz', $result[1][0]);
        $this->assertSame('QUX', $result[1][1]);
    }

    #[TestDox('returns empty when no class constants')]
    public function testReturnsEmptyWhenNoConstants(): void
    {
        $file = PsiFileFactory::fromCode('<?php echo 1;');
        $result = IndexerTestHelper::indexInternal(ClassConstantUsageIndexer::class, $file);

        $this->assertEmpty($result);
    }

    #[TestDox('stores file positions')]
    public function testStoresFilePositions(): void
    {
        $file = PsiFileFactory::fromCode('<?php \Foo::BAR;');
        $result = IndexerTestHelper::indexInternal(ClassConstantUsageIndexer::class, $file);

        $this->assertIsInt($result[0][2]);
    }
}

<?php

declare(strict_types=1);

namespace App\Tests\Unit\Module\Indexing\Indexer;

use App\Module\Indexing\Indexer\ClassUsageIndexer;
use App\Tests\Support\IndexerTestHelper;
use App\Tests\Support\PsiFileFactory;
use App\Tests\TestCase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\TestDox;

#[Group('unit')]
final class ClassUsageIndexerTest extends TestCase
{
    #[TestDox('returns correct key')]
    public function testGetKey(): void
    {
        $this->assertSame('php.classUsages', ClassUsageIndexer::getKey());
    }

    #[TestDox('indexes class usage in new expression')]
    public function testIndexesNewExpression(): void
    {
        $file = PsiFileFactory::fromCode('<?php new \Foo();');
        $result = IndexerTestHelper::indexInternal(ClassUsageIndexer::class, $file);

        $this->assertNotEmpty($result);
        $this->assertSame('Foo', $result[0][0]);
    }

    #[TestDox('indexes class usage in extends clause')]
    public function testIndexesExtendsClause(): void
    {
        $file = PsiFileFactory::fromCode('<?php class Bar extends \Foo {}');
        $result = IndexerTestHelper::indexInternal(ClassUsageIndexer::class, $file);

        $found = array_filter($result, fn ($r) => $r[0] === 'Foo');
        $this->assertNotEmpty($found);
    }

    #[TestDox('indexes class usage in implements clause')]
    public function testIndexesImplementsClause(): void
    {
        $file = PsiFileFactory::fromCode('<?php class Bar implements \Baz {}');
        $result = IndexerTestHelper::indexInternal(ClassUsageIndexer::class, $file);

        $found = array_filter($result, fn ($r) => $r[0] === 'Baz');
        $this->assertNotEmpty($found);
    }

    #[TestDox('indexes class usage in type hint')]
    public function testIndexesTypeHint(): void
    {
        $file = PsiFileFactory::fromCode('<?php function bar(\Foo $x) {}');
        $result = IndexerTestHelper::indexInternal(ClassUsageIndexer::class, $file);

        $found = array_filter($result, fn ($r) => $r[0] === 'Foo');
        $this->assertNotEmpty($found);
    }

    #[TestDox('excludes function calls from class usages')]
    public function testExcludesFunctionCalls(): void
    {
        $file = PsiFileFactory::fromCode('<?php \strlen("test");');
        $result = IndexerTestHelper::indexInternal(ClassUsageIndexer::class, $file);

        $found = array_filter($result, fn ($r) => $r[0] === 'strlen');
        $this->assertEmpty($found);
    }

    #[TestDox('returns empty for file without class usages')]
    public function testReturnsEmptyForNoUsages(): void
    {
        $file = PsiFileFactory::fromCode('<?php echo 1;');
        $result = IndexerTestHelper::indexInternal(ClassUsageIndexer::class, $file);

        $this->assertEmpty($result);
    }

    #[TestDox('indexes multiple class usages')]
    public function testIndexesMultipleUsages(): void
    {
        $file = PsiFileFactory::fromCode('<?php new \Foo(); new \Bar(); new \Foo();');
        $result = IndexerTestHelper::indexInternal(ClassUsageIndexer::class, $file);

        $fooUsages = array_filter($result, fn ($r) => $r[0] === 'Foo');
        $barUsages = array_filter($result, fn ($r) => $r[0] === 'Bar');
        $this->assertCount(2, $fooUsages);
        $this->assertCount(1, $barUsages);
    }
}

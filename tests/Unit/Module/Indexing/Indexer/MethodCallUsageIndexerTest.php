<?php

declare(strict_types=1);

namespace App\Tests\Unit\Module\Indexing\Indexer;

use App\Module\Indexing\Indexer\MethodCallUsageIndexer;
use App\Tests\Support\IndexerTestHelper;
use App\Tests\Support\PsiFileFactory;
use App\Tests\TestCase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\TestDox;

#[Group('unit')]
final class MethodCallUsageIndexerTest extends TestCase
{
    #[TestDox('returns correct key')]
    public function testGetKey(): void
    {
        $this->assertSame('php.methodCallUsages', MethodCallUsageIndexer::getKey());
    }

    #[TestDox('indexes instance method calls')]
    public function testIndexesInstanceMethodCalls(): void
    {
        $file = PsiFileFactory::fromCode('<?php $obj->doSomething();');
        $result = IndexerTestHelper::indexInternal(MethodCallUsageIndexer::class, $file);

        $this->assertNotEmpty($result);
        $this->assertSame('doSomething', $result[0][0]);
        $this->assertNull($result[0][2]);
    }

    #[TestDox('indexes static method calls with class name')]
    public function testIndexesStaticMethodCalls(): void
    {
        $file = PsiFileFactory::fromCode('<?php \Foo::bar();');
        $result = IndexerTestHelper::indexInternal(MethodCallUsageIndexer::class, $file);

        $this->assertNotEmpty($result);
        $this->assertSame('bar', $result[0][0]);
        $this->assertSame('Foo', $result[0][2]);
    }

    #[TestDox('returns empty when no method calls')]
    public function testReturnsEmptyWhenNoCalls(): void
    {
        $file = PsiFileFactory::fromCode('<?php echo 1;');
        $result = IndexerTestHelper::indexInternal(MethodCallUsageIndexer::class, $file);

        $this->assertEmpty($result);
    }

    #[TestDox('indexes multiple method calls')]
    public function testIndexesMultipleCalls(): void
    {
        $file = PsiFileFactory::fromCode('<?php $a->foo(); $b->bar(); \Baz::qux();');
        $result = IndexerTestHelper::indexInternal(MethodCallUsageIndexer::class, $file);

        $this->assertCount(3, $result);
    }
}

<?php

declare(strict_types=1);

namespace App\Tests\Unit\Module\Indexing\Indexer;

use App\Module\Indexing\Indexer\FunctionIndexer;
use App\Tests\Support\IndexerTestHelper;
use App\Tests\Support\PsiFileFactory;
use App\Tests\TestCase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\TestDox;

#[Group('unit')]
final class FunctionIndexerTest extends TestCase
{
    #[TestDox('getKey returns correct key')]
    public function testGetKey(): void
    {
        $this->assertSame('php.functions.fqn', FunctionIndexer::getKey());
    }

    #[TestDox('indexes function names and positions')]
    public function testIndexesFunctions(): void
    {
        $psiFile = PsiFileFactory::fromCode('<?php function foo() {} function bar() {}');
        $results = IndexerTestHelper::indexInternal(FunctionIndexer::class, $psiFile);

        $this->assertCount(2, $results);
        $this->assertArrayHasKey('foo', $results);
        $this->assertArrayHasKey('bar', $results);
        $this->assertSame('foo', $results['foo'][0]);
        $this->assertIsInt($results['foo'][1]);
    }

    #[TestDox('returns empty for file without functions')]
    public function testReturnsEmptyWhenNoFunctions(): void
    {
        $psiFile = PsiFileFactory::fromCode('<?php class Foo {}');
        $results = IndexerTestHelper::indexInternal(FunctionIndexer::class, $psiFile);

        $this->assertEmpty($results);
    }
}

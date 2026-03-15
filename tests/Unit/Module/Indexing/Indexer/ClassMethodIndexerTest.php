<?php

declare(strict_types=1);

namespace App\Tests\Unit\Module\Indexing\Indexer;

use App\Module\Indexing\Indexer\ClassMethodIndexer;
use App\Module\Indexing\Storage\IndexData\MethodData;
use App\Tests\Support\IndexerTestHelper;
use App\Tests\Support\PsiFileFactory;
use App\Tests\TestCase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\TestDox;

#[Group('unit')]
final class ClassMethodIndexerTest extends TestCase
{
    #[TestDox('getKey returns correct key')]
    public function testGetKey(): void
    {
        $this->assertSame('php.classMethods.fqn', ClassMethodIndexer::getKey());
    }

    #[TestDox('indexes class methods')]
    public function testIndexesClassMethods(): void
    {
        $psiFile = PsiFileFactory::fromCode('<?php class Foo { public function bar() {} public function baz() {} }');
        $results = IndexerTestHelper::indexInternal(ClassMethodIndexer::class, $psiFile);

        $this->assertArrayHasKey('Foo::bar', $results);
        $this->assertArrayHasKey('Foo::baz', $results);
        $this->assertInstanceOf(MethodData::class, $results['Foo::bar']);
        $this->assertSame('bar', $results['Foo::bar']->name);
        $this->assertSame('Foo', $results['Foo::bar']->className);
        $this->assertInstanceOf(MethodData::class, $results['Foo::baz']);
        $this->assertSame('baz', $results['Foo::baz']->name);
    }

    #[TestDox('returns empty for file without classes')]
    public function testReturnsEmptyWhenNoClasses(): void
    {
        $psiFile = PsiFileFactory::fromCode('<?php function foo() {}');
        $results = IndexerTestHelper::indexInternal(ClassMethodIndexer::class, $psiFile);

        $this->assertEmpty($results);
    }
}

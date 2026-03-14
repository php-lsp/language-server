<?php

declare(strict_types=1);

namespace App\Tests\Unit\Module\Indexing\Indexer;

use App\Module\Indexing\Indexer\ClassIndexer;
use App\Tests\Support\IndexerTestHelper;
use App\Tests\Support\PsiFileFactory;
use App\Tests\TestCase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\TestDox;

#[Group('unit')]
final class ClassIndexerTest extends TestCase
{
    #[TestDox('getKey returns correct key')]
    public function testGetKey(): void
    {
        $this->assertSame('php.classes.fqn', ClassIndexer::getKey());
    }

    #[TestDox('indexes class names from PHP file')]
    public function testIndexesClasses(): void
    {
        $psiFile = PsiFileFactory::fromCode('<?php class Foo {} class Bar {}');
        $results = IndexerTestHelper::indexInternal(ClassIndexer::class, $psiFile);

        $this->assertCount(2, $results);
        $this->assertContains('Foo', $results);
        $this->assertContains('Bar', $results);
    }

    #[TestDox('returns empty for file without classes')]
    public function testReturnsEmptyWhenNoClasses(): void
    {
        $psiFile = PsiFileFactory::fromCode('<?php function foo() {}');
        $results = IndexerTestHelper::indexInternal(ClassIndexer::class, $psiFile);

        $this->assertEmpty($results);
    }
}

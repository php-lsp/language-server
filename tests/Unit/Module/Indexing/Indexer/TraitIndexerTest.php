<?php

declare(strict_types=1);

namespace App\Tests\Unit\Module\Indexing\Indexer;

use App\Module\Indexing\Indexer\TraitIndexer;
use App\Tests\Support\IndexerTestHelper;
use App\Tests\Support\PsiFileFactory;
use App\Tests\TestCase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\TestDox;

#[Group('unit')]
final class TraitIndexerTest extends TestCase
{
    #[TestDox('getKey returns correct key')]
    public function testGetKey(): void
    {
        $this->assertSame('php.traits.fqn', TraitIndexer::getKey());
    }

    #[TestDox('indexes trait names from PHP file')]
    public function testIndexesTraits(): void
    {
        $psiFile = PsiFileFactory::fromCode('<?php trait Foo {} trait Bar {}');
        $results = IndexerTestHelper::indexInternal(TraitIndexer::class, $psiFile);

        $this->assertCount(2, $results);
        $this->assertContains('Foo', $results);
        $this->assertContains('Bar', $results);
    }

    #[TestDox('returns empty for file without traits')]
    public function testReturnsEmptyWhenNoTraits(): void
    {
        $psiFile = PsiFileFactory::fromCode('<?php class Foo {}');
        $results = IndexerTestHelper::indexInternal(TraitIndexer::class, $psiFile);

        $this->assertEmpty($results);
    }
}

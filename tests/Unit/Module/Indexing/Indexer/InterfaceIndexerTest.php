<?php

declare(strict_types=1);

namespace App\Tests\Unit\Module\Indexing\Indexer;

use App\Module\Indexing\Indexer\InterfaceIndexer;
use App\Module\Indexing\Storage\IndexData\InterfaceData;
use App\Tests\Support\IndexerTestHelper;
use App\Tests\Support\PsiFileFactory;
use App\Tests\TestCase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\TestDox;

#[Group('unit')]
final class InterfaceIndexerTest extends TestCase
{
    #[TestDox('getKey returns correct key')]
    public function testGetKey(): void
    {
        $this->assertSame('php.interfaces.fqn', InterfaceIndexer::getKey());
    }

    #[TestDox('indexes interface names from PHP file')]
    public function testIndexesInterfaces(): void
    {
        $psiFile = PsiFileFactory::fromCode('<?php interface Foo {} interface Bar {}');
        $results = IndexerTestHelper::indexInternal(InterfaceIndexer::class, $psiFile);

        $this->assertCount(2, $results);
        $this->assertArrayHasKey('Foo', $results);
        $this->assertArrayHasKey('Bar', $results);
        $this->assertInstanceOf(InterfaceData::class, $results['Foo']);
        $this->assertSame('Foo', $results['Foo']->fqn);
    }

    #[TestDox('returns empty for file without interfaces')]
    public function testReturnsEmptyWhenNoInterfaces(): void
    {
        $psiFile = PsiFileFactory::fromCode('<?php class Foo {}');
        $results = IndexerTestHelper::indexInternal(InterfaceIndexer::class, $psiFile);

        $this->assertEmpty($results);
    }
}

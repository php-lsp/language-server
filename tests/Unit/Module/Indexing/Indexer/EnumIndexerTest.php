<?php

declare(strict_types=1);

namespace App\Tests\Unit\Module\Indexing\Indexer;

use App\Module\Indexing\Data\EnumData;
use App\Module\Indexing\Indexer\EnumIndexer;
use App\Tests\TestCase;
use App\Tests\Unit\Module\Indexing\IndexerTestHelper;
use PHPUnit\Framework\Attributes\Group;

#[Group('unit')]
final class EnumIndexerTest extends TestCase
{
    public function testGetKey(): void
    {
        $this->assertSame('php.enums.fqn', EnumIndexer::getKey());
    }

    public function testIndexSimpleEnum(): void
    {
        $code = '<?php enum Status { case Active; case Inactive; }';
        $results = IndexerTestHelper::runIndexer(new EnumIndexer($this->createMock(\App\Module\PsiFile\InMemoryPsiFileManager::class)), $code);

        $this->assertCount(1, $results);
        $this->assertArrayHasKey('Status', $results);
        $this->assertInstanceOf(EnumData::class, $results['Status']);
        $this->assertSame('Status', $results['Status']->fqn);
        $this->assertNull($results['Status']->backedType);
        $this->assertSame([], $results['Status']->implements);
    }

    public function testIndexBackedStringEnum(): void
    {
        $code = '<?php enum Color: string { case Red = "red"; }';
        $results = IndexerTestHelper::runIndexer(new EnumIndexer($this->createMock(\App\Module\PsiFile\InMemoryPsiFileManager::class)), $code);

        $this->assertSame('string', $results['Color']->backedType);
    }

    public function testIndexBackedIntEnum(): void
    {
        $code = '<?php enum Priority: int { case High = 1; }';
        $results = IndexerTestHelper::runIndexer(new EnumIndexer($this->createMock(\App\Module\PsiFile\InMemoryPsiFileManager::class)), $code);

        $this->assertSame('int', $results['Priority']->backedType);
    }

    public function testIndexEnumWithImplements(): void
    {
        $code = '<?php enum Status implements HasLabel, HasColor { case Active; }';
        $results = IndexerTestHelper::runIndexer(new EnumIndexer($this->createMock(\App\Module\PsiFile\InMemoryPsiFileManager::class)), $code);

        $this->assertSame(['HasLabel', 'HasColor'], $results['Status']->implements);
    }

    public function testIndexNamespacedEnum(): void
    {
        $code = '<?php namespace App\\Enums; enum Status { case Active; }';
        $results = IndexerTestHelper::runIndexer(new EnumIndexer($this->createMock(\App\Module\PsiFile\InMemoryPsiFileManager::class)), $code);

        $this->assertArrayHasKey('App\\Enums\\Status', $results);
    }

    public function testPositionsAreRecorded(): void
    {
        $code = '<?php enum Foo { case A; }';
        $results = IndexerTestHelper::runIndexer(new EnumIndexer($this->createMock(\App\Module\PsiFile\InMemoryPsiFileManager::class)), $code);

        $this->assertGreaterThanOrEqual(0, $results['Foo']->startPosition);
        $this->assertGreaterThan($results['Foo']->startPosition, $results['Foo']->endPosition);
    }
}

<?php

declare(strict_types=1);

namespace App\Tests\Unit\Module\Indexing\Indexer;

use App\Module\Indexing\Data\InheritanceData;
use App\Module\Indexing\Indexer\InheritanceIndexer;
use App\Tests\TestCase;
use App\Tests\Unit\Module\Indexing\IndexerTestHelper;
use PHPUnit\Framework\Attributes\Group;

#[Group('unit')]
final class InheritanceIndexerTest extends TestCase
{
    public function testGetKey(): void
    {
        $this->assertSame('php.inheritance', InheritanceIndexer::getKey());
    }

    public function testIndexClassExtends(): void
    {
        $code = '<?php class Child extends Base {}';
        $results = IndexerTestHelper::runIndexer(new InheritanceIndexer($this->createMock(\App\Module\PsiFile\InMemoryPsiFileManager::class)), $code);

        $this->assertCount(1, $results);
        $this->assertArrayHasKey('Child', $results);
        $this->assertInstanceOf(InheritanceData::class, $results['Child']);
        $this->assertSame('Child', $results['Child']->fqn);
        $this->assertSame('class', $results['Child']->kind);
        $this->assertSame(['Base'], $results['Child']->parents);
    }

    public function testIndexClassImplements(): void
    {
        $code = '<?php class Foo implements Bar, Baz {}';
        $results = IndexerTestHelper::runIndexer(new InheritanceIndexer($this->createMock(\App\Module\PsiFile\InMemoryPsiFileManager::class)), $code);

        $this->assertSame(['Bar', 'Baz'], $results['Foo']->parents);
    }

    public function testIndexClassExtendsAndImplements(): void
    {
        $code = '<?php class Child extends Base implements Iface {}';
        $results = IndexerTestHelper::runIndexer(new InheritanceIndexer($this->createMock(\App\Module\PsiFile\InMemoryPsiFileManager::class)), $code);

        $this->assertSame(['Base', 'Iface'], $results['Child']->parents);
    }

    public function testClassWithNoParentsSkipped(): void
    {
        $code = '<?php class Standalone {}';
        $results = IndexerTestHelper::runIndexer(new InheritanceIndexer($this->createMock(\App\Module\PsiFile\InMemoryPsiFileManager::class)), $code);

        $this->assertCount(0, $results);
    }

    public function testIndexInterfaceExtends(): void
    {
        $code = '<?php interface Child extends Parent1, Parent2 {}';
        $results = IndexerTestHelper::runIndexer(new InheritanceIndexer($this->createMock(\App\Module\PsiFile\InMemoryPsiFileManager::class)), $code);

        $this->assertSame('interface', $results['Child']->kind);
        $this->assertSame(['Parent1', 'Parent2'], $results['Child']->parents);
    }

    public function testInterfaceWithNoParentsSkipped(): void
    {
        $code = '<?php interface Standalone {}';
        $results = IndexerTestHelper::runIndexer(new InheritanceIndexer($this->createMock(\App\Module\PsiFile\InMemoryPsiFileManager::class)), $code);

        $this->assertCount(0, $results);
    }

    public function testIndexEnumImplements(): void
    {
        $code = '<?php enum Status implements HasLabel { case Active; }';
        $results = IndexerTestHelper::runIndexer(new InheritanceIndexer($this->createMock(\App\Module\PsiFile\InMemoryPsiFileManager::class)), $code);

        $this->assertSame('enum', $results['Status']->kind);
        $this->assertSame(['HasLabel'], $results['Status']->parents);
    }

    public function testEnumWithNoImplementsSkipped(): void
    {
        $code = '<?php enum Status { case Active; }';
        $results = IndexerTestHelper::runIndexer(new InheritanceIndexer($this->createMock(\App\Module\PsiFile\InMemoryPsiFileManager::class)), $code);

        $this->assertCount(0, $results);
    }

    public function testIndexMixedTypes(): void
    {
        $code = '<?php
            class Child extends Base {}
            interface Sub extends Parent_ {}
            enum Status implements HasLabel { case Active; }
        ';
        $results = IndexerTestHelper::runIndexer(new InheritanceIndexer($this->createMock(\App\Module\PsiFile\InMemoryPsiFileManager::class)), $code);

        $this->assertCount(3, $results);
        $this->assertSame('class', $results['Child']->kind);
        $this->assertSame('interface', $results['Sub']->kind);
        $this->assertSame('enum', $results['Status']->kind);
    }
}

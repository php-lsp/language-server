<?php

declare(strict_types=1);

namespace App\Tests\Unit\Module\Indexing\Indexer;

use App\Module\Indexing\Indexer\PropertyAccessUsageIndexer;
use App\Tests\Support\IndexerTestHelper;
use App\Tests\Support\PsiFileFactory;
use App\Tests\TestCase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\TestDox;

#[Group('unit')]
final class PropertyAccessUsageIndexerTest extends TestCase
{
    #[TestDox('returns correct key')]
    public function testGetKey(): void
    {
        $this->assertSame('php.propertyAccessUsages', PropertyAccessUsageIndexer::getKey());
    }

    #[TestDox('indexes instance property access')]
    public function testIndexesInstancePropertyAccess(): void
    {
        $file = PsiFileFactory::fromCode('<?php $obj->name;');
        $result = IndexerTestHelper::indexInternal(PropertyAccessUsageIndexer::class, $file);

        $this->assertNotEmpty($result);
        $values = array_values($result);
        $this->assertSame('name', $values[0][0]);
        $this->assertNull($values[0][2]);
        $this->assertStringContainsString('name@', array_keys($result)[0]);
    }

    #[TestDox('indexes static property access')]
    public function testIndexesStaticPropertyAccess(): void
    {
        $file = PsiFileFactory::fromCode('<?php \Foo::$bar;');
        $result = IndexerTestHelper::indexInternal(PropertyAccessUsageIndexer::class, $file);

        $this->assertNotEmpty($result);
        $values = array_values($result);
        $this->assertSame('bar', $values[0][0]);
        $this->assertSame('Foo', $values[0][2]);
        $this->assertStringContainsString('Foo::$bar@', array_keys($result)[0]);
    }

    #[TestDox('returns empty when no property access')]
    public function testReturnsEmptyWhenNoAccess(): void
    {
        $file = PsiFileFactory::fromCode('<?php echo 1;');
        $result = IndexerTestHelper::indexInternal(PropertyAccessUsageIndexer::class, $file);

        $this->assertEmpty($result);
    }
}

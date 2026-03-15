<?php

declare(strict_types=1);

namespace App\Tests\Unit\Module\PsiFile;

use App\Module\PsiFile\FifoCache;
use App\Tests\TestCase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\TestDox;

#[Group('unit')]
final class FifoCacheTest extends TestCase
{
    #[TestDox('get returns null for missing key')]
    public function testGetMissing(): void
    {
        $cache = new FifoCache(10);
        $this->assertNull($cache->get('missing'));
    }

    #[TestDox('set and get round-trip')]
    public function testSetGet(): void
    {
        $cache = new FifoCache(10);
        $cache->set('key', 'value');
        $this->assertSame('value', $cache->get('key'));
    }

    #[TestDox('has returns correct state')]
    public function testHas(): void
    {
        $cache = new FifoCache(10);
        $this->assertFalse($cache->has('key'));
        $cache->set('key', 'value');
        $this->assertTrue($cache->has('key'));
    }

    #[TestDox('remove deletes entry')]
    public function testRemove(): void
    {
        $cache = new FifoCache(10);
        $cache->set('key', 'value');
        $cache->remove('key');
        $this->assertNull($cache->get('key'));
        $this->assertSame(0, $cache->size());
    }

    #[TestDox('clear removes all entries')]
    public function testClear(): void
    {
        $cache = new FifoCache(10);
        $cache->set('a', 1);
        $cache->set('b', 2);
        $cache->clear();
        $this->assertSame(0, $cache->size());
    }

    #[TestDox('size returns correct count')]
    public function testSize(): void
    {
        $cache = new FifoCache(10);
        $this->assertSame(0, $cache->size());
        $cache->set('a', 1);
        $this->assertSame(1, $cache->size());
        $cache->set('b', 2);
        $this->assertSame(2, $cache->size());
    }

    #[TestDox('evicts oldest entries when max size reached')]
    public function testEviction(): void
    {
        $cache = new FifoCache(3, 0.5);
        $cache->set('a', 1);
        $cache->set('b', 2);
        $cache->set('c', 3);

        // This should trigger eviction of oldest
        $cache->set('d', 4);

        $this->assertFalse($cache->has('a'));
        $this->assertTrue($cache->has('d'));
    }

    #[TestDox('updating existing key does not increase size')]
    public function testUpdateExisting(): void
    {
        $cache = new FifoCache(5);
        $cache->set('key', 'old');
        $cache->set('key', 'new');

        $this->assertSame(1, $cache->size());
        $this->assertSame('new', $cache->get('key'));
    }

    #[TestDox('setPermanent keeps entry from eviction queue')]
    public function testSetPermanent(): void
    {
        $cache = new FifoCache(2, 1.0);
        $cache->setPermanent('protected', 'val');
        $cache->set('normal', 'val2');

        // Trigger eviction — only queue entries are evicted
        $cache->set('new', 'val3');

        $this->assertTrue($cache->has('protected'));
    }
}

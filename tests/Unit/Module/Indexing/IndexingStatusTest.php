<?php

declare(strict_types=1);

namespace App\Tests\Unit\Module\Indexing;

use App\Module\Indexing\IndexingStatus;
use App\Tests\TestCase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\TestDox;

#[Group('unit')]
final class IndexingStatusTest extends TestCase
{
    #[TestDox('initial state is not indexing')]
    public function testInitialState(): void
    {
        $status = new IndexingStatus();
        $data = $status->toArray();

        $this->assertFalse($data['indexing']);
        $this->assertSame(0, $data['filesIndexed']);
        $this->assertNull($data['lastIndexedAt']);
        $this->assertNull($data['lastDuration']);
    }

    #[TestDox('start sets indexing to true')]
    public function testStart(): void
    {
        $status = new IndexingStatus();
        $status->start();

        $this->assertTrue($status->toArray()['indexing']);
    }

    #[TestDox('fileIndexed increments count')]
    public function testFileIndexed(): void
    {
        $status = new IndexingStatus();
        $status->start();
        $status->fileIndexed();
        $status->fileIndexed();
        $status->fileIndexed();

        $this->assertSame(3, $status->toArray()['filesIndexed']);
    }

    #[TestDox('finish sets indexing to false and records duration')]
    public function testFinish(): void
    {
        $status = new IndexingStatus();
        $status->start();
        $status->fileIndexed();
        $status->finish();

        $data = $status->toArray();
        $this->assertFalse($data['indexing']);
        $this->assertNotNull($data['lastIndexedAt']);
        $this->assertNotNull($data['lastDuration']);
        $this->assertGreaterThanOrEqual(0, $data['lastDuration']);
    }

    #[TestDox('start resets file count')]
    public function testStartResetsCount(): void
    {
        $status = new IndexingStatus();
        $status->start();
        $status->fileIndexed();
        $status->fileIndexed();
        $status->finish();

        $status->start();
        $this->assertSame(0, $status->toArray()['filesIndexed']);
    }
}

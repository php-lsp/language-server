<?php

declare(strict_types=1);

namespace App\Tests\Unit\Module\Indexing\Indexer;

use App\Module\Indexing\Indexer\ClassIndexer;
use App\Tests\Support\VirtualFileStub;
use App\Tests\TestCase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\TestDox;

#[Group('unit')]
final class AbstractPhpIndexerTest extends TestCase
{
    #[TestDox('supports returns true for php files')]
    public function testSupportsPhpFiles(): void
    {
        $indexer = (new \ReflectionClass(ClassIndexer::class))->newInstanceWithoutConstructor();

        $this->assertTrue($indexer->supports(VirtualFileStub::create('test.php')));
    }

    #[TestDox('supports returns false for non-php files')]
    public function testDoesNotSupportNonPhpFiles(): void
    {
        $indexer = (new \ReflectionClass(ClassIndexer::class))->newInstanceWithoutConstructor();

        $this->assertFalse($indexer->supports(VirtualFileStub::create('test.txt')));
    }
}

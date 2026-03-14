<?php

declare(strict_types=1);

namespace App\Tests\Unit\Module\Indexing;

use App\Core\Contracts\Indexing\IndexerInterface;
use App\Module\Indexing\Indexer;
use App\Module\Indexing\Storage\StorageInterface;
use App\Tests\Support\MockHelper;
use App\Tests\Support\VirtualFileStub;
use App\Tests\TestCase;
use Lsp\Workspace\File\FileFactoryInterface;
use Lsp\Workspace\File\FilesystemReader\FilesystemReaderFactoryInterface;
use Lsp\Workspace\File\VirtualFileInterface;
use Lsp\Workspace\Project\Project;
use Psr\Log\LoggerInterface;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\TestDox;

#[Group('unit')]
final class IndexerTest extends TestCase
{
    #[TestDox('indexes project files')]
    public function testIndexesProjectFiles(): void
    {
        $storage = $this->createMock(StorageInterface::class);
        $storage->expects($this->atLeastOnce())->method('write');

        $logger = $this->createMock(LoggerInterface::class);
        $fsReaderFactory = $this->createMock(FilesystemReaderFactoryInterface::class);
        $fileFactory = $this->createMock(FileFactoryInterface::class);

        // Create a VirtualFile with no children (leaf file)
        $phpFile = VirtualFileStub::create('test.php');

        // Create an inline indexer that supports php files
        $indexer = new class implements IndexerInterface {
            public static function getKey(): string { return 'test.key'; }
            public function supports(VirtualFileInterface $file): bool { return true; }
            public function index(VirtualFileInterface $file): iterable { return ['data']; }
        };

        // Create a project with a real uri via reflection
        $projectRef = new \ReflectionClass(Project::class);
        $project = $projectRef->newInstanceWithoutConstructor();
        $uriProp = $projectRef->getProperty('uri');
        $uriProp->setValue($project, \Lsp\Workspace\Uri\Uri::createLocal('/tmp/project'));

        // Mock Project as IteratorAggregate
        $projectMock = $this->createMock(Project::class);
        $projectMock->method('getIterator')->willReturn(new \ArrayIterator([$phpFile]));

        // Since we can't easily stub readonly uri on mock, use reflection approach
        $uriProp2 = $projectRef->getProperty('uri');
        $uriProp2->setValue($projectMock, \Lsp\Workspace\Uri\Uri::createLocal('/tmp/project'));

        // Create stub file for stubs directory
        $stubDir = VirtualFileStub::create('node_modules');
        $fileFactory->method('create')->willReturn($stubDir);

        $mainIndexer = new Indexer([$indexer], $storage, $logger, $fsReaderFactory, $fileFactory);

        $mainIndexer->index($projectMock);
    }

    #[TestDox('skips ignored directories')]
    public function testSkipsIgnoredDirectories(): void
    {
        $storage = $this->createMock(StorageInterface::class);
        $storage->expects($this->never())->method('write');

        $logger = $this->createMock(LoggerInterface::class);
        $fsReaderFactory = $this->createMock(FilesystemReaderFactoryInterface::class);
        $fileFactory = $this->createMock(FileFactoryInterface::class);

        // Create a directory named "node_modules" which should be skipped
        $ignoredDir = VirtualFileStub::create('node_modules');

        $projectRef = new \ReflectionClass(Project::class);
        $project = $this->createMock(Project::class);
        $project->method('getIterator')->willReturn(new \ArrayIterator([$ignoredDir]));
        $projectRef->getProperty('uri')->setValue($project, \Lsp\Workspace\Uri\Uri::createLocal('/tmp/project'));

        // For stubs directory
        $stubDir = VirtualFileStub::create('.git');
        $fileFactory->method('create')->willReturn($stubDir);

        $mainIndexer = new Indexer([], $storage, $logger, $fsReaderFactory, $fileFactory);

        $mainIndexer->index($project);
    }
}

<?php

declare(strict_types=1);

namespace App\Tests\Unit\Module\Indexing;

use App\Module\Indexing\IndexerFileCollector;
use App\Tests\Support\VirtualFileStub;
use App\Tests\TestCase;
use Lsp\Workspace\File\FileFactoryInterface;
use Lsp\Workspace\File\FilesystemReader\FilesystemReaderFactoryInterface;
use Lsp\Workspace\Project\Project;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\TestDox;

#[Group('unit')]
final class IndexerFileCollectorTest extends TestCase
{
    #[TestDox('collects leaf files from project')]
    public function testCollectsLeafFiles(): void
    {
        $fsReaderFactory = $this->createMock(FilesystemReaderFactoryInterface::class);
        $fileFactory = $this->createMock(FileFactoryInterface::class);

        $phpFile = VirtualFileStub::create('test.php');
        $stubDir = VirtualFileStub::create('node_modules');
        $fileFactory->method('create')->willReturn($stubDir);

        $projectRef = new \ReflectionClass(Project::class);
        $project = $this->createMock(Project::class);
        $project->method('getIterator')->willReturn(new \ArrayIterator([$phpFile]));
        $projectRef->getProperty('uri')->setValue(
            $project,
            \Lsp\Workspace\Uri\Uri::createLocal('/tmp/project'),
        );

        $collector = new IndexerFileCollector($fsReaderFactory, $fileFactory);
        $files = $collector->collect($project);

        $this->assertCount(1, $files);
        $this->assertSame('test.php', $files[0]->name);
    }

    #[TestDox('skips ignored directories')]
    public function testSkipsIgnoredDirectories(): void
    {
        $fsReaderFactory = $this->createMock(FilesystemReaderFactoryInterface::class);
        $fileFactory = $this->createMock(FileFactoryInterface::class);

        $ignoredDir = VirtualFileStub::create('node_modules');
        $gitDir = VirtualFileStub::create('.git');
        $fileFactory->method('create')->willReturn($gitDir);

        $projectRef = new \ReflectionClass(Project::class);
        $project = $this->createMock(Project::class);
        $project->method('getIterator')->willReturn(new \ArrayIterator([$ignoredDir]));
        $projectRef->getProperty('uri')->setValue(
            $project,
            \Lsp\Workspace\Uri\Uri::createLocal('/tmp/project'),
        );

        $collector = new IndexerFileCollector($fsReaderFactory, $fileFactory);
        $files = $collector->collect($project);

        $this->assertEmpty($files);
    }
}

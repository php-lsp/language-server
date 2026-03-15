<?php

declare(strict_types=1);

namespace App\Tests\Unit\Module\Indexing;

use App\Core\Contracts\Indexing\IndexerInterface;
use App\Module\Indexing\Indexer;
use App\Module\Indexing\IndexerFileCollector;
use App\Module\Indexing\IndexingStatus;
use App\Module\Indexing\Storage\InMemoryStorage;
use App\Module\Notification\ActiveConnectionProvider;
use App\Module\Notification\ProgressNotifier;
use App\Module\Notification\ServerNotificationSender;
use App\Tests\Support\MockHelper;
use App\Tests\Support\VirtualFileStub;
use App\Tests\TestCase;
use Lsp\Dispatcher\Result\Provider\ResultProviderInterface;
use Lsp\Workspace\File\VirtualFileInterface;
use Lsp\Workspace\Project\Project;
use Psr\Log\LoggerInterface;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\TestDox;

#[Group('unit')]
final class IndexerTest extends TestCase
{
    private function createProgressNotifier(): ProgressNotifier
    {
        $connectionProvider = new ActiveConnectionProvider();
        $resultProvider = $this->createMock(ResultProviderInterface::class);
        $resultProvider->method('getResult')->willReturn([]);
        $logger = $this->createMock(LoggerInterface::class);
        $sender = new ServerNotificationSender($connectionProvider, $resultProvider, $logger);

        return new ProgressNotifier($sender);
    }

    #[TestDox('indexes project files')]
    public function testIndexesProjectFiles(): void
    {
        $storage = new InMemoryStorage();
        $logger = $this->createMock(LoggerInterface::class);

        $phpFile = VirtualFileStub::create('test.php');

        $indexer = new class implements IndexerInterface {
            public static function getKey(): string { return 'test.key'; }
            public function supports(VirtualFileInterface $file): bool { return true; }
            public function index(VirtualFileInterface $file): iterable { return ['data']; }
        };

        $projectMock = $this->createMock(Project::class);
        $projectMock->method('getIterator')->willReturn(new \ArrayIterator([$phpFile]));
        (new \ReflectionProperty(Project::class, 'uri'))
            ->setValue($projectMock, \Lsp\Workspace\Uri\Uri::createLocal('/tmp/project'));

        $fileCollector = MockHelper::mock(IndexerFileCollector::class);
        $fileCollector->method('collect')->willReturn([$phpFile]);

        $mainIndexer = new Indexer(
            [$indexer],
            $storage,
            $logger,
            $fileCollector,
            $this->createProgressNotifier(),
            new IndexingStatus(),
        );

        $mainIndexer->index($projectMock);

        $this->assertGreaterThan(0, $storage->count('test.key'));
    }

    #[TestDox('skips unsupported files in runIndexers')]
    public function testSkipsUnsupportedFiles(): void
    {
        $storage = new InMemoryStorage();
        $logger = $this->createMock(LoggerInterface::class);

        $phpFile = VirtualFileStub::create('readme.txt');

        $indexer = new class implements IndexerInterface {
            public static function getKey(): string { return 'test.key'; }
            public function supports(VirtualFileInterface $file): bool { return false; }
            public function index(VirtualFileInterface $file): iterable { return []; }
        };

        $fileCollector = MockHelper::mock(IndexerFileCollector::class);
        $fileCollector->method('collect')->willReturn([$phpFile]);

        $projectMock = $this->createMock(Project::class);
        $projectMock->method('getIterator')->willReturn(new \ArrayIterator([]));
        (new \ReflectionProperty(Project::class, 'uri'))
            ->setValue($projectMock, \Lsp\Workspace\Uri\Uri::createLocal('/tmp/project'));

        $mainIndexer = new Indexer(
            [$indexer],
            $storage,
            $logger,
            $fileCollector,
            $this->createProgressNotifier(),
            new IndexingStatus(),
        );

        $mainIndexer->index($projectMock);

        $this->assertSame(0, $storage->count('test.key'));
    }

    #[TestDox('skips ignored directories')]
    public function testSkipsIgnoredDirectories(): void
    {
        $storage = new InMemoryStorage();
        $logger = $this->createMock(LoggerInterface::class);

        $project = $this->createMock(Project::class);
        $project->method('getIterator')->willReturn(new \ArrayIterator([]));
        (new \ReflectionProperty(Project::class, 'uri'))
            ->setValue($project, \Lsp\Workspace\Uri\Uri::createLocal('/tmp/project'));

        $fileCollector = MockHelper::mock(IndexerFileCollector::class);
        $fileCollector->method('collect')->willReturn([]);

        $mainIndexer = new Indexer(
            [],
            $storage,
            $logger,
            $fileCollector,
            $this->createProgressNotifier(),
            new IndexingStatus(),
        );

        $mainIndexer->index($project);

        $this->assertSame([], $storage->getIndexKeys());
    }
}

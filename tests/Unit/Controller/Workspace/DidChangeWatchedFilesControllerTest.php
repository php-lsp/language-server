<?php

declare(strict_types=1);

namespace App\Tests\Unit\Controller\Workspace;

use App\Controller\Workspace\DidChangeWatchedFilesController;
use App\Module\Indexing\Indexer;
use App\Module\Indexing\IndexerFileCollector;
use App\Module\Indexing\IndexingStatus;
use App\Module\Indexing\Storage\StorageInterface;
use App\Module\Notification\ActiveConnectionProvider;
use App\Module\Notification\ProgressNotifier;
use App\Module\Notification\ServerNotificationSender;
use App\Tests\Support\MockHelper;
use App\Tests\Support\VirtualFileStub;
use App\Tests\TestCase;
use Lsp\Dispatcher\Result\Provider\ResultProviderInterface;
use Lsp\Protocol\Type\DidChangeWatchedFilesParams;
use Lsp\Protocol\Type\FileChangeType;
use Lsp\Protocol\Type\FileEvent;
use Lsp\Workspace\File\FileFactoryInterface;
use Lsp\Workspace\File\FilesystemReader\FilesystemReaderFactoryInterface;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;

#[Group('unit')]
final class DidChangeWatchedFilesControllerTest extends TestCase
{
    private StorageInterface&MockObject $storage;
    private FileFactoryInterface&MockObject $fileFactory;
    private FilesystemReaderFactoryInterface&MockObject $filesystemReaderFactory;
    private LoggerInterface&MockObject $logger;
    private DidChangeWatchedFilesController $controller;

    protected function setUp(): void
    {
        parent::setUp();

        $this->storage = $this->createMock(StorageInterface::class);
        $this->fileFactory = $this->createMock(FileFactoryInterface::class);
        $this->filesystemReaderFactory = $this->createMock(FilesystemReaderFactoryInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        $indexer = $this->createRealIndexer();

        $this->controller = new DidChangeWatchedFilesController(
            $indexer,
            $this->storage,
            $this->fileFactory,
            $this->filesystemReaderFactory,
            $this->logger,
        );
    }

    #[TestDox('re-indexes file when Created event is received')]
    public function testCreatedFileIsReindexed(): void
    {
        $uri = 'file:///project/src/Foo.php';
        $file = VirtualFileStub::create('Foo.php');

        $this->fileFactory->expects($this->once())
            ->method('create')
            ->willReturn($file);

        // Indexer::reindexFile calls deleteByUri internally before re-indexing
        $this->storage->expects($this->once())
            ->method('deleteByUri');

        $params = new DidChangeWatchedFilesParams([
            new FileEvent($uri, FileChangeType::Created),
        ]);

        ($this->controller)($params);
    }

    #[TestDox('re-indexes file when Changed event is received')]
    public function testChangedFileIsReindexed(): void
    {
        $uri = 'file:///project/src/Bar.php';
        $file = VirtualFileStub::create('Bar.php');

        $this->fileFactory->expects($this->once())
            ->method('create')
            ->willReturn($file);

        $this->storage->expects($this->once())
            ->method('deleteByUri');

        $params = new DidChangeWatchedFilesParams([
            new FileEvent($uri, FileChangeType::Changed),
        ]);

        ($this->controller)($params);
    }

    #[TestDox('deletes index entries when Deleted event is received')]
    public function testDeletedFileRemovesIndexEntries(): void
    {
        $uri = 'file:///project/src/Baz.php';

        $this->storage->expects($this->once())
            ->method('deleteByUri')
            ->with($uri);

        $this->fileFactory->expects($this->never())
            ->method('create');

        $params = new DidChangeWatchedFilesParams([
            new FileEvent($uri, FileChangeType::Deleted),
        ]);

        ($this->controller)($params);
    }

    #[TestDox('ignores non-PHP files')]
    public function testIgnoresNonPhpFiles(): void
    {
        $this->storage->expects($this->never())
            ->method('deleteByUri');

        $this->fileFactory->expects($this->never())
            ->method('create');

        $params = new DidChangeWatchedFilesParams([
            new FileEvent('file:///project/README.md', FileChangeType::Changed),
            new FileEvent('file:///project/config.json', FileChangeType::Created),
            new FileEvent('file:///project/style.css', FileChangeType::Deleted),
        ]);

        ($this->controller)($params);
    }

    #[TestDox('handles multiple file events in a single notification')]
    public function testHandlesMultipleEvents(): void
    {
        $file = VirtualFileStub::create('test.php');

        $this->fileFactory->expects($this->exactly(2))
            ->method('create')
            ->willReturn($file);

        // deleteByUri is called 3 times: once for Deleted event, twice by Indexer::reindexFile
        $this->storage->expects($this->exactly(3))
            ->method('deleteByUri');

        $params = new DidChangeWatchedFilesParams([
            new FileEvent('file:///project/src/Created.php', FileChangeType::Created),
            new FileEvent('file:///project/src/Changed.php', FileChangeType::Changed),
            new FileEvent('file:///project/src/Deleted.php', FileChangeType::Deleted),
        ]);

        ($this->controller)($params);
    }

    #[TestDox('logs warning and continues when a file event fails')]
    public function testLogsWarningOnFailure(): void
    {
        $file = VirtualFileStub::create('Good.php');

        $this->fileFactory->expects($this->exactly(2))
            ->method('create')
            ->willReturnCallback(function () use ($file) {
                static $call = 0;
                $call++;
                if ($call === 1) {
                    throw new \RuntimeException('File not found');
                }

                return $file;
            });

        $this->logger->expects($this->once())
            ->method('warning');

        $params = new DidChangeWatchedFilesParams([
            new FileEvent('file:///project/src/Bad.php', FileChangeType::Created),
            new FileEvent('file:///project/src/Good.php', FileChangeType::Created),
        ]);

        ($this->controller)($params);
    }

    #[TestDox('handles empty changes list')]
    public function testHandlesEmptyChanges(): void
    {
        $this->storage->expects($this->never())
            ->method('deleteByUri');

        $this->fileFactory->expects($this->never())
            ->method('create');

        $params = new DidChangeWatchedFilesParams([]);

        ($this->controller)($params);
    }

    private function createRealIndexer(): Indexer
    {
        $connectionProvider = new ActiveConnectionProvider();
        $resultProvider = $this->createMock(ResultProviderInterface::class);
        $resultProvider->method('getResult')->willReturn([]);
        $sender = new ServerNotificationSender($connectionProvider, $resultProvider, $this->logger);
        $progressNotifier = new ProgressNotifier($sender);

        $fileCollector = MockHelper::mock(IndexerFileCollector::class);
        $fileCollector->method('collect')->willReturn([]);

        return new Indexer(
            [],
            $this->storage,
            $this->logger,
            $fileCollector,
            $progressNotifier,
            new IndexingStatus(),
        );
    }
}

<?php

declare(strict_types=1);

namespace App\Controller\Workspace;

use App\Core\UriHelper;
use App\Module\Indexing\Indexer;
use App\Module\Indexing\Storage\StorageInterface;
use Lsp\Kernel\Attribute\AsController;
use Lsp\Protocol\Type\DidChangeWatchedFilesParams;
use Lsp\Protocol\Type\FileChangeType;
use Lsp\Router\Attribute\Route;
use Lsp\Workspace\File\FileFactoryInterface;
use Lsp\Workspace\File\FilesystemReader\FilesystemReaderFactoryInterface;
use Lsp\Workspace\Uri\Uri;
use Psr\Log\LoggerInterface;

#[AsController, Route('workspace/didChangeWatchedFiles')]
final class DidChangeWatchedFilesController
{
    public function __construct(
        private readonly Indexer $indexer,
        private readonly StorageInterface $storage,
        private readonly FileFactoryInterface $fileFactory,
        private readonly FilesystemReaderFactoryInterface $filesystemReaderFactory,
        private readonly LoggerInterface $logger,
    ) {}

    public function __invoke(DidChangeWatchedFilesParams $params): void
    {
        foreach ($params->changes as $event) {
            if (!str_ends_with($event->uri, '.php')) {
                continue;
            }

            try {
                $this->handleFileEvent($event->uri, $event->type);
            } catch (\Throwable $e) {
                $this->logger->warning('Failed to handle watched file change for {uri}: {error}', [
                    'uri' => $event->uri,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    /**
     * @param non-empty-string $uri
     */
    private function handleFileEvent(string $uri, FileChangeType $type): void
    {
        if ($type === FileChangeType::Deleted) {
            $this->storage->deleteByUri($uri);
            $this->logger->debug('Removed index entries for deleted file: {uri}', ['uri' => $uri]);

            return;
        }

        // Created or Changed — re-index the file
        $filePath = UriHelper::toFilePath($uri);
        if ($filePath === null) {
            return;
        }

        $lspUri = Uri::createLocal($uri);
        $file = $this->fileFactory->create($lspUri->path, $this->filesystemReaderFactory);

        $this->indexer->reindexFile($file);
    }
}

<?php

declare(strict_types=1);

namespace App\Listener;

use App\Core\Event\Document\DocumentSaved;
use App\Core\UriHelper;
use App\Module\Indexing\Indexer;
use Lsp\Extension\DocumentManager\Editor\Document\Document;
use Lsp\Workspace\File\FileFactoryInterface;
use Lsp\Workspace\File\FilesystemReader\FilesystemReaderFactoryInterface;
use Lsp\Workspace\Uri\Uri;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

final class IncrementalIndexListener
{
    public function __construct(
        private readonly Indexer $indexer,
        private readonly FileFactoryInterface $fileFactory,
        private readonly FilesystemReaderFactoryInterface $filesystemReaderFactory,
        private readonly LoggerInterface $logger,
    ) {}

    #[AsEventListener]
    public function onDocumentSaved(DocumentSaved $event): void
    {
        $this->reindex($event->document);
    }

    private function reindex(Document $document): void
    {
        $uriString = (string) $document->uri;
        if (!str_ends_with($uriString, '.php')) {
            return;
        }

        try {
            $filePath = UriHelper::toFilePath($uriString);
            if ($filePath === null || !file_exists($filePath)) {
                return;
            }

            $uri = Uri::createLocal($uriString);
            $file = $this->fileFactory->create($uri->path, $this->filesystemReaderFactory);

            $this->indexer->reindexFile($file);
        } catch (\Throwable $e) {
            $this->logger->warning('Incremental indexing failed for {uri}: {error}', [
                'uri' => $uriString,
                'error' => $e->getMessage(),
            ]);
        }
    }
}

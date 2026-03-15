<?php

declare(strict_types=1);

namespace App\Module\Indexing;

use App\Core\Contracts\Indexing\IndexerInterface;
use App\Module\Indexing\Storage\StorageInterface;
use App\Module\Notification\ProgressNotifier;
use Lsp\Workspace\File\VirtualFileInterface;
use Lsp\Workspace\Project\Project;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;

use function React\Async\async;
use function React\Async\await;

final class Indexer
{
    private const string PROGRESS_ID = 'indexing';

    /** @var IndexerInterface[] */
    private array $indexers;

    public function __construct(
        #[AutowireIterator('lsp.indexers')]
        iterable $indexers,
        private StorageInterface $storage,
        private LoggerInterface $logger,
        private IndexerFileCollector $fileCollector,
        private ProgressNotifier $progressNotifier,
        private readonly IndexingStatus $indexingStatus,
    ) {
        $this->indexers = iterator_to_array($indexers);
    }

    public function index(Project $project): void
    {
        $this->logger->info('Indexing project: {uri}', ['uri' => (string) $project->uri]);

        $this->indexingStatus->start();

        $this->progressNotifier->create(self::PROGRESS_ID);
        $this->progressNotifier->begin(
            token: self::PROGRESS_ID,
            title: 'Indexing',
            message: 'Collecting files...',
            percentage: 0,
        );

        $filesToIndex = $this->fileCollector->collect($project);
        $total = \count($filesToIndex);

        $this->progressNotifier->report(
            token: self::PROGRESS_ID,
            message: \sprintf('Indexing %d files...', $total),
            percentage: 0,
        );

        $indexed = 0;
        foreach ($filesToIndex as $file) {
            $this->runIndexers($file);
            $this->indexingStatus->fileIndexed();
            $indexed++;

            if (($indexed % 50) === 0 || $indexed === $total) {
                $percentage = $total > 0 ? (int) (($indexed / $total) * 100) : 100;
                $this->progressNotifier->report(
                    token: self::PROGRESS_ID,
                    message: \sprintf('%d/%d files', $indexed, $total),
                    percentage: $percentage,
                );
            }
        }

        $this->progressNotifier->end(
            token: self::PROGRESS_ID,
            message: \sprintf('Indexed %d files', $total),
        );

        $this->indexingStatus->finish();

        $this->logger->info('Indexing finished: {count} files indexed', ['count' => $total]);
    }

    public function reindexFile(VirtualFileInterface $file): void
    {
        $uri = (string) $file->uri;
        $this->storage->deleteByUri($uri);
        $this->runIndexers($file);
        $this->logger->debug('Re-indexed file: {uri}', ['uri' => $uri]);
    }

    private function runIndexers(VirtualFileInterface $file): void
    {
        foreach ($this->indexers as $indexer) {
            if (!$indexer->supports($file)) {
                continue;
            }

            await(
                async(function () use ($file, $indexer) {
                    $key = $indexer::getKey();
                    $map = $indexer->index($file);

                    $this->storage->write($key, $map, (string) $file->uri);
                })(),
            );
        }
    }
}

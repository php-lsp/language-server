<?php

namespace App\Module\Indexing;

use App\Core\Contracts\Indexing\IndexerInterface;
use App\Module\Indexing\Storage\StorageInterface;
use Lsp\Workspace\File\VirtualFileInterface;
use Lsp\Workspace\Project\Project;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;

final class Indexer
{
    /** @var IndexerInterface[] */
    private array $indexers;

    public function __construct(
        #[AutowireIterator('lsp.indexers')]
        iterable $indexers,
        private StorageInterface $storage,
        private LoggerInterface $logger,
    )
    {
        $this->indexers = iterator_to_array($indexers);
    }

    public function index(Project $project): void
    {
        $this->logger->info('Indexing project: ' . $project->uri);
        foreach ($project as $file) {
            $this->walkFilesInternal($file, 0);
        }

//        $this->walkFilesInternal($file, $level);
        $this->logger->info('Indexing finished');
    }

    private function walkFilesInternal(VirtualFileInterface $file, int $level): void
    {
        $ignored = [
            'node_modules',
            '.git',
            '.idea',
            'config',
            'resources',
            'runtime',
            'vendor',
        ];
        if (in_array($file->name, $ignored)) {
//            echo str_repeat('  ', $level) . '- ' . $file . " --- skipping ---\n";
            return;
        }

        $this->logger->info(str_repeat(' ', $level) . $file);

        if ($file->count() === 0) {
            $this->runIndexers($file);
        }

        foreach ($file as $child) {
            $this->walkFilesInternal($child, $level + 1);
        }
    }

    private function runIndexers(VirtualFileInterface $file): void
    {
        foreach ($this->indexers as $indexer) {
            if ($indexer->supports($file)) {
                $key = $indexer::getKey();
                $map = $indexer->index($file);

                $this->storage->write($key, $map, $file->uri);
            }
        }
    }
}

<?php

namespace App\Module\Indexing;

use App\Module\Indexing\Indexer\ClassIndexer;
use App\Module\Indexing\Storage\Entry;
use App\Module\Indexing\Storage\InMemoryStorage;
use App\Module\Indexing\Storage\StorageInterface;
use App\Module\PsiFile\PHPPsiFileParser;
use Lsp\Protocol\Type\WorkspaceFolder;
use Lsp\Workspace\File\VirtualFileInterface;
use Lsp\Workspace\Project\Project;
use Lsp\Workspace\Project\ProjectFactory;
use PhpParser\Node\Stmt\Class_;
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
        $this->logger->info('Indexing finished');
    }

    private function walkFilesInternal(VirtualFileInterface $file, int $level): void
    {
        if ($file->name === 'node_modules' || $file->name === '.git' || $file->name === '.idea') {
//            echo str_repeat('  ', $level) . '- ' . $file . " --- skipping ---\n";
            return;
        }

//        echo str_repeat('  ', $level) . '- ' . $file . "\n";

        foreach ($file as $child) {
            $this->runIndexers($child);
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

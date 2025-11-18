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
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;

final class IndexLookup
{
    public function __construct(
        private readonly StorageInterface $storage,
    )
    {
    }

    /**
     * @template T
     * @param class-string<IndexerInterface<T>> $indexerClass
     * @return iterable<Entry<T>>
     */
    public function findByKey(string $indexerClass): iterable
    {
        foreach ($this->storage->read($indexerClass::getKey()) as $entry) {
            yield $entry;
        }
    }
}

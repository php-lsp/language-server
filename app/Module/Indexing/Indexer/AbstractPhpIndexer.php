<?php

namespace App\Module\Indexing\Indexer;

use App\Core\Contracts\Indexing\IndexerInterface;
use App\Module\PsiFile\InMemoryPsiFileManager;
use App\Module\PsiFile\PHPPsiFile;
use Lsp\Workspace\File\VirtualFileInterface;

/**
 * @template TValue
 * @implements IndexerInterface<TValue>
 */
abstract class AbstractPhpIndexer implements IndexerInterface
{
    public function __construct(
        private InMemoryPsiFileManager $fileManager,
    )
    {
    }

    public function supports(VirtualFileInterface $file): bool
    {
        return $file->extension === 'php';
    }

    /**
     * @return array<TValue>
     */
    public function index(VirtualFileInterface $file): iterable
    {
        $phpFile = $this->fileManager->findPsiFileByUri($file->uri);

        return $this->indexInternal($phpFile);
    }

    /**
     * @return array<TValue>
     */
    abstract protected function indexInternal(PHPPsiFile $phpFile): array;
}

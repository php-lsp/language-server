<?php

namespace App\Module\Indexing\Indexer;

use App\Module\Indexing\IndexerInterface;
use App\Module\PsiFile\PHPPsiFile;
use App\Module\PsiFile\PHPPsiFileParser;
use Lsp\Extension\DocumentManager\Editor\Document\DocumentFactory;
use Lsp\Extension\DocumentManager\Editor\Document\UriFactory;
use Lsp\Workspace\File\VirtualFileInterface;
use Throwable;

/**
 * @template TValue
 * @implements IndexerInterface<TValue>
 */
abstract class AbstractPhpIndexer implements IndexerInterface
{
    public function __construct(
        private PHPPsiFileParser $parser,
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
    public function index(VirtualFileInterface $file): array
    {
        try {
            $content = file_get_contents($file->path);
        } catch (Throwable $e) {
            dump($e, $file);
            return [];
        }

        $documentFactory = new DocumentFactory(new UriFactory());
        $document = $documentFactory->create($file->uri, $content);

        $root = $this->parser->parse($document);
        $phpFile = new PHPPsiFile($root);

        return $this->indexInternal($phpFile);
    }

    /**
     * @return array<TValue>
     */
    abstract protected function indexInternal(PHPPsiFile $phpFile): array;
}

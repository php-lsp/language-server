<?php

namespace App\Module\Indexing\Indexer;

use App\Core\Contracts\Indexing\IndexerInterface;
use App\Module\PsiFile\PHPPsiFile;
use App\Module\PsiFile\PHPPsiFileParser;
use Lsp\Extension\DocumentManager\Editor\Document\DocumentFactory;
use Lsp\Extension\DocumentManager\Editor\Document\DocumentFactoryInterface;
use Lsp\Extension\DocumentManager\Editor\Document\UriFactory;
use Lsp\Workspace\File\FilesystemReader\FilesystemReaderFactoryInterface;
use Lsp\Workspace\File\FilesystemReader\FilesystemReaderInterface;
use Lsp\Workspace\File\VirtualFileInterface;
use Throwable;
use function React\Async\await;

/**
 * @template TValue
 * @implements IndexerInterface<TValue>
 */
abstract class AbstractPhpIndexer implements IndexerInterface
{
    public function __construct(
        private PHPPsiFileParser $parser,
        private DocumentFactoryInterface $documentFactory,
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
        try {
            $content = file_get_contents($file->path);
        } catch (Throwable $e) {
            dump($e, $file);
            return [];
        }

        $document = $this->documentFactory->create($file->uri, $content);

        $root = $this->parser->parse($document);
        $phpFile = new PHPPsiFile($root);

        return $this->indexInternal($phpFile);
    }

    /**
     * @return array<TValue>
     */
    abstract protected function indexInternal(PHPPsiFile $phpFile): array;
}

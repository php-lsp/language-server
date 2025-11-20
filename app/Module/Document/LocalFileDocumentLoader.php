<?php
declare(strict_types=1);

namespace App\Module\Document;

use Lsp\Extension\DocumentManager\Editor\Document\Document;
use Lsp\Extension\DocumentManager\Editor\Document\DocumentFactoryInterface;
use Lsp\Protocol\Type\TextDocumentIdentifier;

final class LocalFileDocumentLoader implements DocumentLoaderInterface
{
    public function __construct(
        private readonly DocumentFactoryInterface $documentFactory
    )
    {
    }

    public function load(TextDocumentIdentifier $identifier): Document
    {
        $content = file_get_contents($identifier->uri);
        return $this->documentFactory->create($identifier->uri, $content);
    }
}

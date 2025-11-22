<?php
declare(strict_types=1);

namespace App\Module\Document;

use Lsp\Extension\DocumentManager\Editor\Document\Document;
use Lsp\Extension\DocumentManager\Editor\Document\DocumentFactoryInterface;
use Lsp\Protocol\Type\TextDocumentIdentifier;
use Lsp\Workspace\Uri\Uri;
use function React\Async\await;

final class LocalFileDocumentLoader implements DocumentLoaderInterface
{
    public function __construct(
        private readonly DocumentFactoryInterface $documentFactory,
        private \React\Filesystem\AdapterInterface $adapter,
    )
    {
    }

    public function load(Uri|TextDocumentIdentifier $identifier): Document
    {
        $uri = match (true) {
            $identifier instanceof TextDocumentIdentifier => $identifier->uri,
            $identifier instanceof Uri => (string)$identifier,
            default => throw new \InvalidArgumentException('Unsupported identifier ' . get_debug_type($identifier)),
        };

        $content = await($this->adapter->file($uri)->getContents());

        return $this->documentFactory->create($uri, $content);
    }
}

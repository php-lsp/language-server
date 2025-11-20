<?php
declare(strict_types=1);

namespace App\Module\Document;

use Lsp\Protocol\Type\TextDocumentIdentifier;
use Lsp\Workspace\Uri\Uri;

class InMemoryDocumentIdentifierFactory implements DocumentIdentifierFactoryInterface
{
    private array $cache = [];

    public function create(string $path): TextDocumentIdentifier
    {
        if (isset($this->cache[$path])) {
            return $this->cache[$path];
        }

        $uri = $path;
        if (!str_starts_with($path, 'file://')) {
            $uri = (string)Uri::createLocal($path);
        }

        return $this->cache[$path] ??= new TextDocumentIdentifier($uri);
    }
}

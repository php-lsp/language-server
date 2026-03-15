<?php

declare(strict_types=1);

namespace App\Module\Document;

use App\Module\PsiFile\FifoCache;
use Lsp\Protocol\Type\TextDocumentIdentifier;
use Lsp\Workspace\Uri\Uri;
use Override;

class InMemoryDocumentIdentifierFactory implements DocumentIdentifierFactoryInterface
{
    /**
     * @var FifoCache<TextDocumentIdentifier>
     */
    private FifoCache $cache;

    public function __construct()
    {
        $this->cache = new FifoCache(300);
    }

    #[Override]
    public function create(string $path): TextDocumentIdentifier
    {
        $value = $this->cache[$path];
        if ($value !== null) {
            return $value;
        }

        $uri = $path;
        if (!str_starts_with($path, 'file://')) {
            $uri = (string) Uri::createLocal($path);
        }

        $value = new TextDocumentIdentifier($uri);
        $this->cache->set($path, $value);

        return $value;
    }
}

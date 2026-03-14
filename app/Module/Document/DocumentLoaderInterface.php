<?php

declare(strict_types=1);

namespace App\Module\Document;

use Lsp\Extension\DocumentManager\Editor\Document\Document;
use Lsp\Protocol\Type\TextDocumentIdentifier;
use Lsp\Workspace\Uri\Uri;

interface DocumentLoaderInterface
{
    public function load(Uri|TextDocumentIdentifier $identifier): Document;
}

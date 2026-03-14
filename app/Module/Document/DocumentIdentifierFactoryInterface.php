<?php

declare(strict_types=1);

namespace App\Module\Document;

use Lsp\Protocol\Type\TextDocumentIdentifier;

interface DocumentIdentifierFactoryInterface
{
    public function create(string $path): TextDocumentIdentifier;
}

<?php

declare(strict_types=1);

namespace App\Core\Contracts\Documentation;

use Lsp\Extension\DocumentManager\Editor\EditorInterface;
use Lsp\Protocol\Type\Position;
use Lsp\Protocol\Type\TextDocumentIdentifier;

class DocumentationContext
{
    public function __construct(
        public TextDocumentIdentifier $textDocumentIdentifier,
        public Position $position,
        public EditorInterface $editor,
    ) {}
}

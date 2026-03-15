<?php

declare(strict_types=1);

namespace App\Core\Contracts\Definition;

use Lsp\Extension\DocumentManager\Editor\EditorInterface;
use Lsp\Protocol\Type\Position;
use Lsp\Protocol\Type\TextDocumentIdentifier;

readonly class DefinitionContext
{
    public function __construct(
        public TextDocumentIdentifier $textDocumentIdentifier,
        public Position $position,
        public EditorInterface $editor,
    ) {}
}

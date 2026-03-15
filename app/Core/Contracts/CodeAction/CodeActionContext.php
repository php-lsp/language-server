<?php

declare(strict_types=1);

namespace App\Core\Contracts\CodeAction;

use Lsp\Extension\DocumentManager\Editor\EditorInterface;
use Lsp\Protocol\Type\CodeActionContext as LspCodeActionContext;
use Lsp\Protocol\Type\Range;
use Lsp\Protocol\Type\TextDocumentIdentifier;

readonly class CodeActionContext
{
    public function __construct(
        public TextDocumentIdentifier $textDocumentIdentifier,
        public Range $range,
        public LspCodeActionContext $lspContext,
        public EditorInterface $editor,
    ) {}
}

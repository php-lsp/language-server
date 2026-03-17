<?php

declare(strict_types=1);

namespace App\Core\Contracts\DocumentSymbol;

use App\Core\Contracts\PsiFile\PsiFileManagerInterface;
use Lsp\Extension\DocumentManager\Editor\EditorInterface;
use Lsp\Protocol\Type\TextDocumentIdentifier;

readonly class DocumentSymbolContext
{
    public function __construct(
        public TextDocumentIdentifier $textDocumentIdentifier,
        public EditorInterface $editor,
        public PsiFileManagerInterface $fileManager,
    ) {}
}

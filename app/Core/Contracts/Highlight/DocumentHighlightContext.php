<?php

declare(strict_types=1);

namespace App\Core\Contracts\Highlight;

use App\Core\Contracts\PsiFile\PsiFileManagerInterface;
use Lsp\Extension\DocumentManager\Editor\EditorInterface;
use Lsp\Protocol\Type\Position;
use Lsp\Protocol\Type\TextDocumentIdentifier;

readonly class DocumentHighlightContext
{
    public function __construct(
        public TextDocumentIdentifier $textDocumentIdentifier,
        public Position $position,
        public EditorInterface $editor,
        public PsiFileManagerInterface $fileManager,
    ) {}
}

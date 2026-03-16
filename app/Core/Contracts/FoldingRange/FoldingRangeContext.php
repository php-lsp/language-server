<?php

declare(strict_types=1);

namespace App\Core\Contracts\FoldingRange;

use App\Module\PsiFile\InMemoryPsiFileManager;
use Lsp\Extension\DocumentManager\Editor\EditorInterface;
use Lsp\Protocol\Type\TextDocumentIdentifier;

readonly class FoldingRangeContext
{
    public function __construct(
        public TextDocumentIdentifier $textDocumentIdentifier,
        public EditorInterface $editor,
        public InMemoryPsiFileManager $fileManager,
    ) {}
}

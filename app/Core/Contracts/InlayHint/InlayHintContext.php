<?php

declare(strict_types=1);

namespace App\Core\Contracts\InlayHint;

use App\Module\PsiFile\InMemoryPsiFileManager;
use Lsp\Extension\DocumentManager\Editor\EditorInterface;
use Lsp\Protocol\Type\Range;
use Lsp\Protocol\Type\TextDocumentIdentifier;

readonly class InlayHintContext
{
    public function __construct(
        public TextDocumentIdentifier $textDocumentIdentifier,
        public Range $range,
        public EditorInterface $editor,
        public InMemoryPsiFileManager $fileManager,
    ) {}
}

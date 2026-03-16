<?php

declare(strict_types=1);

namespace App\Core\Contracts\Completion;

use App\Core\Contracts\PsiFile\PsiFileManagerInterface;
use Lsp\Extension\DocumentManager\Editor\EditorInterface;
use Lsp\Protocol\Type\Position;
use Lsp\Protocol\Type\TextDocumentIdentifier;
use PhpParser\Node;

class CompletionContext
{
    public function __construct(
        public TextDocumentIdentifier $textDocumentIdentifier,
        public Position $position,
        public EditorInterface $editor,
        public PsiFileManagerInterface $fileManager,
    ) {}

    public function currentNode(): ?Node
    {
        $file = $this->fileManager->findPsiFile($this->editor, $this->textDocumentIdentifier);
        if ($file === null) {
            return null;
        }

        return $file->findLastAtPosition($this->position);
    }
}

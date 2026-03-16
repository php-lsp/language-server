<?php

declare(strict_types=1);

namespace App\Core\Contracts\PsiFile;

use Lsp\Extension\DocumentManager\Editor\EditorInterface;
use Lsp\Protocol\Type\TextDocumentIdentifier;

interface PsiFileManagerInterface
{
    public function findPsiFile(EditorInterface $editor, TextDocumentIdentifier $identifier): ?PsiFileInterface;

    public function invalidate(string $uri): void;
}

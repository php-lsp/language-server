<?php

declare(strict_types=1);

namespace App\Core\Contracts\PsiFile;

use App\Module\PsiFile\PHPPsiFile;
use Lsp\Extension\DocumentManager\Editor\EditorInterface;
use Lsp\Protocol\Type\TextDocumentIdentifier;

interface PsiFileManagerInterface
{
    public function findPsiFile(EditorInterface $editor, TextDocumentIdentifier $identifier): ?PHPPsiFile;

    public function invalidate(string $uri): void;
}

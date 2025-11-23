<?php

declare(strict_types=1);

namespace App\Controller\TextDocument;

use App\Module\PsiFile\InMemoryPsiFileManager;
use App\Module\PsiFile\Tree;
use Lsp\Extension\DocumentManager\Editor\EditorInterface;
use Lsp\Kernel\Attribute\AsController;
use Lsp\Protocol\Type\PrepareRenameParams;
use Lsp\Router\Attribute\Route;

#[AsController, Route('textDocument/prepareRename')]
final class PrepareRenameController
{
    public function __construct(
        private InMemoryPsiFileManager $fileManager,
    )
    {
    }

    public function __invoke(EditorInterface $editor, PrepareRenameParams $params)
    {
        $file = $this->fileManager->findPsiFile($editor, $params->textDocument);
        if ($file === null) {
            return null;
        }

        $element = $file->findLastAtPosition($params->position);

        return Tree::getRange($element, $file);
    }
}

<?php

declare(strict_types=1);

namespace App\Controller\TextDocument;

use App\Module\PsiFile\InMemoryPsiFileManager;
use App\Module\PsiFile\Tree;
use Lsp\Extension\DocumentManager\Editor\EditorInterface;
use Lsp\Kernel\Attribute\AsController;
use Lsp\Protocol\Type\PrepareRenameParams;
use Lsp\Protocol\Type\Range;
use Lsp\Router\Attribute\Route;
use PhpParser\Node;

#[AsController, Route('textDocument/prepareRename')]
final class PrepareRenameController
{
    public function __construct(
        private InMemoryPsiFileManager $fileManager,
    ) {}

    public function __invoke(EditorInterface $editor, PrepareRenameParams $params): ?Range
    {
        $file = $this->fileManager->findPsiFile($editor, $params->textDocument);
        if ($file === null) {
            return null;
        }

        $element = $file->findLastAtPosition($params->position);
        if ($element === null) {
            return null;
        }

        // Only allow rename on identifiable symbols
        if ($element instanceof Node\Expr\Variable && is_string($element->name)) {
            return Tree::getRange($element, $file);
        }

        if ($element instanceof Node\Identifier) {
            return Tree::getRange($element, $file);
        }

        if ($element instanceof Node\Name) {
            return Tree::getRange($element, $file);
        }

        return null;
    }
}

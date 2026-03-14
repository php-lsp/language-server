<?php

namespace App\Controller\TextDocument;

use App\Module\PsiFile\InMemoryPsiFileManager;
use App\Module\PsiFile\PHPPsiFile;
use App\Module\PsiFile\Tree;
use Lsp\Extension\DocumentManager\Editor\EditorInterface;
use Lsp\Kernel\Attribute\AsController;
use Lsp\Protocol\Type\DocumentSymbol;
use Lsp\Protocol\Type\DocumentSymbolParams;
use Lsp\Protocol\Type\SymbolKind;
use Lsp\Router\Attribute\Route;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Function_;
use PhpParser\Node\Stmt\Property;

// #[AsController, Route('textDocument/documentSymbol')]
final class DocumentSymbolController
{
    public function __construct(
        private InMemoryPsiFileManager $fileManager,
    ) {}

    public function __invoke(EditorInterface $editor, DocumentSymbolParams $request): array
    {
        $file = $this->fileManager->findPsiFile($editor, $request->textDocument);

        $symbols = [];

        foreach ($file->ast->children as $stmt) {
            if ($stmt instanceof Class_) {
                $symbols[] = new DocumentSymbol(
                    name: $stmt->name->toString(),
                    kind: SymbolKind::ClassKind,
                    range: Tree::getRange($stmt, $file),
                    selectionRange: Tree::getRange($stmt->name, $file),
                    children: $this->getClassMembers($stmt, $file),
                );
            }

            if ($stmt instanceof Function_) {
                $symbols[] = new DocumentSymbol(
                    name: $stmt->name->toString(),
                    kind: SymbolKind::FunctionKind,
                    range: Tree::getRange($stmt, $file),
                    selectionRange: Tree::getRange($stmt->name, $file),
                );
            }
        }

        return $symbols;
    }

    private function getClassMembers(Class_ $classNode, PHPPsiFile $file): array
    {
        $members = [];

        foreach ($classNode->stmts as $stmt) {
            if ($stmt instanceof ClassMethod) {
                $members[] = new DocumentSymbol(
                    name: $stmt->name->toString(),
                    kind: SymbolKind::MethodKind,
                    range: Tree::getRange($stmt, $file),
                    selectionRange: Tree::getRange($stmt->name, $file),
                );
            }

            if ($stmt instanceof Property) {
                foreach ($stmt->props as $prop) {
                    $members[] = new DocumentSymbol(
                        name: '$' . $prop->name->toString(),
                        kind: SymbolKind::PropertyKind,
                        range: Tree::getRange($stmt, $file),
                        selectionRange: Tree::getRange($prop, $file),
                    );
                }
            }
        }

        return $members;
    }
}

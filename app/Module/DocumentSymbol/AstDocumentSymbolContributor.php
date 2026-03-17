<?php

declare(strict_types=1);

namespace App\Module\DocumentSymbol;

use App\Core\Contracts\DocumentSymbol\AsDocumentSymbolContributor;
use App\Core\Contracts\DocumentSymbol\DocumentSymbolConsumer;
use App\Core\Contracts\DocumentSymbol\DocumentSymbolContext;
use App\Core\Contracts\DocumentSymbol\DocumentSymbolContributor;
use App\Core\Contracts\PsiFile\PsiFileInterface;
use App\Module\PsiFile\Tree;
use Lsp\Protocol\Type\DocumentSymbol;
use Lsp\Protocol\Type\SymbolKind;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\ClassConst;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Enum_;
use PhpParser\Node\Stmt\EnumCase;
use PhpParser\Node\Stmt\Function_;
use PhpParser\Node\Stmt\Interface_;
use PhpParser\Node\Stmt\Namespace_;
use PhpParser\Node\Stmt\Property;
use PhpParser\Node\Stmt\Trait_;

#[AsDocumentSymbolContributor]
final class AstDocumentSymbolContributor implements DocumentSymbolContributor
{
    public function contribute(DocumentSymbolContext $context, DocumentSymbolConsumer $consumer): void
    {
        $file = $context->fileManager->findPsiFile($context->editor, $context->textDocumentIdentifier);

        foreach ($file->getChildren() as $stmt) {
            if ($stmt instanceof Namespace_) {
                foreach ($stmt->stmts ?? [] as $nsStmt) {
                    $symbol = $this->processStatement($nsStmt, $file);
                    if ($symbol !== null) {
                        $consumer($symbol);
                    }
                }

                continue;
            }

            $symbol = $this->processStatement($stmt, $file);
            if ($symbol !== null) {
                $consumer($symbol);
            }
        }
    }

    private function processStatement(mixed $stmt, PsiFileInterface $file): ?DocumentSymbol
    {
        if ($stmt instanceof Class_) {
            return new DocumentSymbol(
                name: $stmt->name?->toString() ?? '<anonymous>',
                kind: SymbolKind::ClassKind,
                range: Tree::getRange($stmt, $file),
                selectionRange: $stmt->name !== null
                    ? Tree::getRange($stmt->name, $file)
                    : Tree::getRange($stmt, $file),
                children: $this->getClassLikeMembers($stmt->stmts, $file),
            );
        }

        if ($stmt instanceof Interface_) {
            return new DocumentSymbol(
                name: $stmt->name->toString(),
                kind: SymbolKind::InterfaceKind,
                range: Tree::getRange($stmt, $file),
                selectionRange: Tree::getRange($stmt->name, $file),
                children: $this->getClassLikeMembers($stmt->stmts, $file),
            );
        }

        if ($stmt instanceof Trait_) {
            return new DocumentSymbol(
                name: $stmt->name->toString(),
                kind: SymbolKind::ClassKind,
                range: Tree::getRange($stmt, $file),
                selectionRange: Tree::getRange($stmt->name, $file),
                detail: 'trait',
                children: $this->getClassLikeMembers($stmt->stmts, $file),
            );
        }

        if ($stmt instanceof Enum_) {
            return new DocumentSymbol(
                name: $stmt->name->toString(),
                kind: SymbolKind::EnumKind,
                range: Tree::getRange($stmt, $file),
                selectionRange: Tree::getRange($stmt->name, $file),
                children: $this->getEnumMembers($stmt, $file),
            );
        }

        if ($stmt instanceof Function_) {
            return new DocumentSymbol(
                name: $stmt->name->toString(),
                kind: SymbolKind::FunctionKind,
                range: Tree::getRange($stmt, $file),
                selectionRange: Tree::getRange($stmt->name, $file),
            );
        }

        return null;
    }

    /**
     * @param array<mixed> $stmts
     *
     * @return list<DocumentSymbol>
     */
    private function getClassLikeMembers(array $stmts, PsiFileInterface $file): array
    {
        $members = [];

        foreach ($stmts as $stmt) {
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

            if ($stmt instanceof ClassConst) {
                foreach ($stmt->consts as $const) {
                    $members[] = new DocumentSymbol(
                        name: $const->name->toString(),
                        kind: SymbolKind::ConstantKind,
                        range: Tree::getRange($stmt, $file),
                        selectionRange: Tree::getRange($const->name, $file),
                    );
                }
            }
        }

        return $members;
    }

    /**
     * @return list<DocumentSymbol>
     */
    private function getEnumMembers(Enum_ $enum, PsiFileInterface $file): array
    {
        $members = [];

        foreach ($enum->stmts as $stmt) {
            if ($stmt instanceof EnumCase) {
                $members[] = new DocumentSymbol(
                    name: $stmt->name->toString(),
                    kind: SymbolKind::EnumMemberKind,
                    range: Tree::getRange($stmt, $file),
                    selectionRange: Tree::getRange($stmt->name, $file),
                );
            }

            if ($stmt instanceof ClassMethod) {
                $members[] = new DocumentSymbol(
                    name: $stmt->name->toString(),
                    kind: SymbolKind::MethodKind,
                    range: Tree::getRange($stmt, $file),
                    selectionRange: Tree::getRange($stmt->name, $file),
                );
            }

            if ($stmt instanceof ClassConst) {
                foreach ($stmt->consts as $const) {
                    $members[] = new DocumentSymbol(
                        name: $const->name->toString(),
                        kind: SymbolKind::ConstantKind,
                        range: Tree::getRange($stmt, $file),
                        selectionRange: Tree::getRange($const->name, $file),
                    );
                }
            }
        }

        return $members;
    }
}

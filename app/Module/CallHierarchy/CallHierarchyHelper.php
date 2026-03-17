<?php

declare(strict_types=1);

namespace App\Module\CallHierarchy;

use App\Module\Indexing\Data\FunctionData;
use App\Module\Indexing\Data\MethodData;
use App\Module\PsiFile\PHPPsiFile;
use App\Module\PsiFile\Tree;
use Lsp\Extension\DocumentManager\Editor\Document\Document;
use Lsp\Protocol\Type\Position;
use Lsp\Protocol\Type\Range;
use Lsp\Protocol\Type\SymbolKind;
use PhpParser\Node;

final class CallHierarchyHelper
{
    /**
     * @return array{name: string, kind: SymbolKind, range: Range, selectionRange: Range, data: array<string, string>}|null
     */
    public static function findEnclosingCallable(PHPPsiFile $file, int $pos): ?array
    {
        $nodes = $file->findAtPosition($pos);
        foreach (array_reverse($nodes) as $node) {
            if ($node instanceof Node\Stmt\Function_) {
                $name = $node->namespacedName?->toString() ?? $node->name->toString();
                $range = Tree::getRange($node, $file);
                $selectionRange = Tree::getRange($node->name, $file);

                return [
                    'name' => $name,
                    'kind' => SymbolKind::FunctionKind,
                    'range' => $range,
                    'selectionRange' => $selectionRange,
                    'data' => ['type' => 'function', 'name' => $name],
                ];
            }
            if ($node instanceof Node\Stmt\ClassMethod) {
                $classNode =
                    Tree::parentOfType($node, Node\Stmt\Class_::class) ?? Tree::parentOfType(
                        $node,
                        Node\Stmt\Interface_::class,
                    ) ?? Tree::parentOfType($node, Node\Stmt\Trait_::class);
                $className = $classNode?->namespacedName?->toString() ?? $classNode?->name?->toString() ?? '';
                $methodName = $node->name->toString();
                $range = Tree::getRange($node, $file);
                $selectionRange = Tree::getRange($node->name, $file);

                return [
                    'name' => $className . '::' . $methodName,
                    'kind' => SymbolKind::MethodKind,
                    'range' => $range,
                    'selectionRange' => $selectionRange,
                    'data' => ['type' => 'method', 'name' => $methodName, 'class' => $className],
                ];
            }
        }

        return null;
    }

    public static function makeNameRange(Document $document, int $byteOffset, int $nameLength): Range
    {
        [$startLine, $startCol] = Tree::toLineColumn($document, $byteOffset);
        [$endLine, $endCol] = Tree::toLineColumn($document, $byteOffset + $nameLength);

        return new Range(
            new Position($startLine, $startCol),
            new Position($endLine, $endCol),
        );
    }

    public static function findFunctionNameRange(PHPPsiFile $file, FunctionData $data): ?Range
    {
        $nodes = $file->findAtPosition($data->startPosition);
        foreach (array_reverse($nodes) as $node) {
            if (!$node instanceof Node\Stmt\Function_) {
                continue;
            }

            $fqn = $node->namespacedName?->toString() ?? $node->name->toString();
            if ($fqn === $data->fqn) {
                return Tree::getRange($node->name, $file);
            }
        }

        return null;
    }

    public static function findMethodNameRange(PHPPsiFile $file, MethodData $data): ?Range
    {
        $nodes = $file->findAtPosition($data->startPosition);
        foreach (array_reverse($nodes) as $node) {
            if ($node instanceof Node\Stmt\ClassMethod && $node->name->toString() === $data->name) {
                return Tree::getRange($node->name, $file);
            }
        }

        return null;
    }
}

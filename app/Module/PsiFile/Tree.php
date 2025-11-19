<?php
declare(strict_types=1);

namespace App\Module\PsiFile;

use Lsp\Extension\DocumentManager\Editor\Document\Document;
use Lsp\Protocol\Type\Position;
use Lsp\Protocol\Type\Range;
use Phplrt\Contracts\Source\SourceExceptionInterface;
use PhpParser\Node;

class Tree
{
    /**
     * @template T of Node
     * @param class-string<T> $class
     * @return T|null
     */
    public static function parentOfType(?Node $node, string $class): object|null
    {
        while ($node = $node?->getAttribute('parent')) {
            if ($node instanceof $class) {
                return $node;
            }
        }

        return null;
    }

    /**
     * @template T of Node
     * @param class-string<T> $class
     * @return T[]
     */
    public static function childrenOfType(Node|SourceFileRoot|null $node, string $class): array
    {
        if ($node === null) {
            return [];
        }
        if ($node instanceof SourceFileRoot) {
            $result = [];
            foreach ($node->children as $child) {
                $result = array_merge($result, self::childrenOfType($child, $class));
            }
            return $result;
        }

        $result = [];
        if ($node instanceof $class) {
            $result[] = $node;
        }

        $children = self::getNodeChildren($node);

        foreach ($children as $child) {
            if ($child instanceof $class) {
                $result[] = $child;
            }
            $result = array_merge($result, self::childrenOfType($child, $class));
        }

        return $result;
    }

    /**
     * @return array<Node>
     */
    private static function getNodeChildren(Node $node): array
    {
        return match (true) {
            $node instanceof Node\Stmt\ClassLike => $node->stmts,
            $node instanceof Node\Stmt\Namespace_ => $node->stmts,
            $node instanceof Node\Stmt\Function_ => $node->stmts,
            $node instanceof Node\Stmt\ClassMethod => $node->stmts,
            default => [],
        } ?? [];
    }

    public static function getRange(Node $node, PHPPsiFile $file): Range
    {
        return new Range(
            start: new Position(
                $node->getStartLine() - 1,
                self::toColumn($file->ast->document, $node->getStartFilePos() - 1),
            ),
            end: new Position(
                $node->getEndLine() - 1,
                self::toColumn($file->ast->document, $node->getEndFilePos() - 1),
            ),
        );
    }

    public static function toColumn(Document $document, int $pos): int
    {
        $text = $document->getContents();
        if ($pos > strlen($text)) {
            throw new \RuntimeException('Invalid position information');
        }

        $lineStartPos = strrpos($text, "\n", $pos - strlen($text));
        if (false === $lineStartPos) {
            $lineStartPos = -1;
        }

        return $pos - $lineStartPos;
    }

    /**
     * @return array{int, int}
     */
    public static function toLineColumn(Document $document, int $pos): array
    {
        $text = $document->getContents();
        if ($pos > strlen($text)) {
            throw new \RuntimeException('Invalid position information');
        }

        $line = substr_count($text, "\n", 0, $pos);

        $lineStartPos = strrpos($text, "\n", $pos - strlen($text));
        if (false === $lineStartPos) {
            $lineStartPos = -1;
        }

        $column = $pos - $lineStartPos - 1;

        return [$line, $column];
    }
}

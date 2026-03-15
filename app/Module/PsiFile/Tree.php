<?php

declare(strict_types=1);

namespace App\Module\PsiFile;

use Lsp\Extension\DocumentManager\Editor\Document\Document;
use Lsp\Protocol\Type\Position;
use Lsp\Protocol\Type\Range;
use PhpParser\Node;

class Tree
{
    /**
     * @template T of Node
     *
     * @param class-string<T> $class
     *
     * @return T|null
     */
    public static function parentOfType(?Node $node, string $class): ?object
    {
        while ($node = $node?->getAttribute('parent')) {
            if ($node instanceof $class) {
                return $node;
            }
        }

        return null;
    }

    public static function parent(Node $node): ?Node
    {
        return $node->getAttribute('parent');
    }

    /**
     * @template T of Node
     *
     * @param class-string<T> $class
     *
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
            $result = array_merge($result, self::childrenOfType($child, $class));
        }

        return $result;
    }

    /**
     * @template T of Node
     *
     * @param class-string<T> ...$classes
     *
     * @return T[]
     */
    public static function childrenOfTypes(Node|SourceFileRoot|null $node, string ...$classes): array
    {
        return self::childrenOfTypesInternal($node, $classes);
    }

    /**
     * @template T of Node
     *
     * @param list<class-string<T>> $classes
     *
     * @return T[]
     */
    private static function childrenOfTypesInternal(Node|SourceFileRoot|null $node, array $classes): array
    {
        if ($node === null) {
            return [];
        }
        if ($node instanceof SourceFileRoot) {
            $result = [];
            foreach ($node->children as $child) {
                $result = array_merge($result, self::childrenOfTypesInternal($child, $classes));
            }

            return $result;
        }

        $result = [];
        foreach ($classes as $class) {
            if (!$node instanceof $class) {
                continue;
            }

            $result[] = $node;
            break;
        }

        $children = self::getNodeChildren($node);

        foreach ($children as $child) {
            $result = array_merge($result, self::childrenOfTypesInternal($child, $classes));
        }

        return $result;
    }

    /**
     * @return array<Node>
     */
    private static function getNodeChildren(Node $node): array
    {
        return (
            match (true) {
                $node instanceof Node\Stmt\ClassLike => $node->stmts,
                $node instanceof Node\Stmt\Namespace_ => $node->stmts,
                $node instanceof Node\Stmt\Function_ => $node->stmts,
                $node instanceof Node\Stmt\ClassMethod => $node->stmts,
                default => [],
            } ?? []
        );
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
                self::toColumn($file->ast->document, $node->getEndFilePos()),
            ),
        );
    }

    public static function toColumn(Document $document, int $pos): int
    {
        $text = $document->getContents();
        if ($pos > strlen($text)) {
            throw new \RuntimeException('Invalid position information');
        }

        $needle = "\n";
        $lineStartPos = strrpos($text, $needle, $pos - strlen($text));
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

        $needle = "\n";
        $line = substr_count($text, $needle, offset: 0, length: $pos);

        $lineStartPos = strrpos($text, $needle, $pos - strlen($text));
        if (false === $lineStartPos) {
            $lineStartPos = -1;
        }

        $column = $pos - $lineStartPos - 1;

        return [$line, $column];
    }

    public static function getParentNodes(Node $node, int $limit = 100): iterable
    {
        $parent = $node->getAttribute('parent');
        $count = 0;
        while ($parent && $count++ < $limit) {
            yield $parent;
            $parent = $parent->getAttribute('parent');
        }
    }

    public static function getParentNodesIncluding(Node $node, int $limit = 100): iterable
    {
        yield $node;
        yield from self::getParentNodes($node, $limit);
    }

    public static function toString(?Node $element): string
    {
        if ($element === null) {
            return '';
        }

        if ($element instanceof \Stringable) {
            return (string) $element;
        }

        return '-----' . var_export($element, return: true) . '-----';
    }
}

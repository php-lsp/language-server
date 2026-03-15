<?php

declare(strict_types=1);

namespace App\Module\PsiFile;

use Lsp\Extension\DocumentManager\Editor\Document\Document;
use Lsp\Protocol\Type\Position;
use Lsp\Protocol\Type\Range;
use PhpParser\Error;
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
        $node = $node?->getAttribute('parent');
        while ($node !== null) {
            if ($node instanceof $class) {
                return $node;
            }
            $node = $node->getAttribute('parent');
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
        $result = [];
        self::collectChildrenOfType($node, $class, $result);

        return $result;
    }

    /**
     * @template TCollect of Node
     *
     * @param class-string<TCollect> $class
     * @param list<TCollect> $result
     */
    private static function collectChildrenOfType(Node|SourceFileRoot|null $node, string $class, array &$result): void
    {
        if ($node === null) {
            return;
        }

        if ($node instanceof SourceFileRoot) {
            foreach ($node->children as $child) {
                self::collectChildrenOfType($child, $class, $result);
            }

            return;
        }

        if ($node instanceof $class) {
            $result[] = $node;
        }

        foreach (self::getNodeChildren($node) as $child) {
            self::collectChildrenOfType($child, $class, $result);
        }
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
        $result = [];
        self::collectChildrenOfTypes($node, $classes, $result);

        return $result;
    }

    /**
     * @param list<class-string<Node>> $classes
     * @param list<Node> $result
     */
    private static function collectChildrenOfTypes(Node|SourceFileRoot|null $node, array $classes, array &$result): void
    {
        if ($node === null) {
            return;
        }

        if ($node instanceof SourceFileRoot) {
            foreach ($node->children as $child) {
                self::collectChildrenOfTypes($child, $classes, $result);
            }

            return;
        }

        foreach ($classes as $class) {
            if (!$node instanceof $class) {
                continue;
            }

            $result[] = $node;
            break;
        }

        foreach (self::getNodeChildren($node) as $child) {
            self::collectChildrenOfTypes($child, $classes, $result);
        }
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

    /**
     * Convert a php-parser 1-based line number to a 0-based LSP line number.
     */
    public static function toLspLine(int $parserLine): int
    {
        return $parserLine - 1;
    }

    /**
     * Convert a 0-based LSP line number to a php-parser 1-based line number.
     */
    public static function toParserLine(int $lspLine): int
    {
        return $lspLine + 1;
    }

    /**
     * Get the 0-based LSP start line of a php-parser node.
     */
    public static function nodeStartLine(Node $node): int
    {
        return $node->getStartLine() - 1;
    }

    /**
     * Get the 0-based LSP end line of a php-parser node.
     */
    public static function nodeEndLine(Node $node): int
    {
        return $node->getEndLine() - 1;
    }

    /**
     * Convert a php-parser Error to an LSP Range.
     */
    public static function errorRange(Error $error, Document $document): Range
    {
        $contents = $document->getContents();

        return new Range(
            start: new Position($error->getStartLine() - 1, $error->getStartColumn($contents)),
            end: new Position($error->getEndLine() - 1, $error->getEndColumn($contents)),
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
     * @var array<string, list<int>> Cached line start offsets keyed by URI+version.
     */
    private static array $lineOffsetCache = [];

    /**
     * @return list<int>
     */
    private static function getLineOffsets(Document $document): array
    {
        $cacheKey = spl_object_id($document) . ':' . $document->version;

        if (array_key_exists($cacheKey, self::$lineOffsetCache)) {
            return self::$lineOffsetCache[$cacheKey];
        }

        $text = $document->getContents();
        $offsets = [0];
        $offset = 0;
        $newline = "\n";

        $pos = strpos($text, $newline, $offset);
        while ($pos !== false) {
            $offsets[] = $pos + 1;
            $offset = $pos + 1;
            $pos = strpos($text, $newline, $offset);
        }

        self::$lineOffsetCache[$cacheKey] = $offsets;

        return $offsets;
    }

    public static function clearLineOffsetCache(): void
    {
        self::$lineOffsetCache = [];
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

        $offsets = self::getLineOffsets($document);

        // Binary search for the line containing $pos
        $lo = 0;
        $hi = count($offsets) - 1;
        while ($lo < $hi) {
            $mid = ($lo + $hi + 1) >> 1;
            match ($offsets[$mid] <= $pos) {
                true => $lo = $mid,
                false => $hi = $mid - 1,
            };
        }

        return [$lo, $pos - $offsets[$lo]];
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

        return $element->getType();
    }
}

<?php
declare(strict_types=1);

namespace App\Module\PsiFile;

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
    private static function getNodeChildren(Node $node):array
    {
        return match (true) {
            $node instanceof Node\Stmt\ClassLike => $node->stmts,
            $node instanceof Node\Stmt\Namespace_ => $node->stmts,
            default => [],
        };
    }
}

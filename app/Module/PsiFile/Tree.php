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
}

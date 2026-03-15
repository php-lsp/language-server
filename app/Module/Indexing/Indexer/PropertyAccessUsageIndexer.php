<?php

declare(strict_types=1);

namespace App\Module\Indexing\Indexer;

use App\Core\Contracts\Indexing\AsIndexer;
use App\Module\PsiFile\PHPPsiFile;
use Override;
use PhpParser\Node;
use PhpParser\NodeFinder;

#[AsIndexer]
/**
 * Indexes all property access usages (instance and static).
 *
 * @extends AbstractPhpIndexer<array{string, int, ?string}>
 */
class PropertyAccessUsageIndexer extends AbstractPhpIndexer
{
    #[Override]
    public static function getKey(): string
    {
        return 'php.propertyAccessUsages';
    }

    #[Override]
    protected function indexInternal(PHPPsiFile $phpFile): array
    {
        $finder = new NodeFinder();

        $results = [];

        $instanceAccesses = $finder->findInstanceOf($phpFile->ast->children, Node\Expr\PropertyFetch::class);
        foreach ($instanceAccesses as $access) {
            if (!$access->name instanceof Node\Identifier) {
                continue;
            }

            $results[] = [$access->name->toString(), $access->name->getStartFilePos(), null];
        }

        $staticAccesses = $finder->findInstanceOf($phpFile->ast->children, Node\Expr\StaticPropertyFetch::class);
        foreach ($staticAccesses as $access) {
            if (!$access->name instanceof Node\VarLikeIdentifier) {
                continue;
            }

            $className = $access->class instanceof Node\Name ? $access->class->toString() : null;
            $results[] = [$access->name->toString(), $access->name->getStartFilePos(), $className];
        }

        return $results;
    }
}

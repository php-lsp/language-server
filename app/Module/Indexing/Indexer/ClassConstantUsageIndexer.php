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
 * Indexes all class constant fetch usages.
 *
 * @extends AbstractPhpIndexer<array{string, string, int}>
 */
class ClassConstantUsageIndexer extends AbstractPhpIndexer
{
    #[Override]
    public static function getKey(): string
    {
        return 'php.classConstantUsages';
    }

    #[Override]
    protected function indexInternal(PHPPsiFile $phpFile): array
    {
        $finder = new NodeFinder();

        $fetches = $finder->findInstanceOf($phpFile->ast->children, Node\Expr\ClassConstFetch::class);

        $results = [];
        foreach ($fetches as $fetch) {
            if (!$fetch->class instanceof Node\Name) {
                continue;
            }
            if (!$fetch->name instanceof Node\Identifier) {
                continue;
            }

            $results[] = [$fetch->class->toString(), $fetch->name->toString(), $fetch->getStartFilePos()];
        }

        return $results;
    }
}

<?php

declare(strict_types=1);

namespace App\Module\Indexing\Indexer;

use App\Core\Contracts\Indexing\AsIndexer;
use App\Module\Indexing\Data\TraitData;
use App\Module\PsiFile\PHPPsiFile;
use App\Module\PsiFile\Tree;
use Override;
use PhpParser\Node\Stmt\Trait_;

/**
 * @extends AbstractPhpIndexer<TraitData>
 */
#[AsIndexer]
class TraitIndexer extends AbstractPhpIndexer
{
    #[Override]
    public static function getKey(): string
    {
        return 'php.traits.fqn';
    }

    #[Override]
    protected function indexInternal(PHPPsiFile $phpFile): array
    {
        $traits = Tree::childrenOfType($phpFile->ast, Trait_::class);

        $results = [];
        foreach ($traits as $trait) {
            if ($trait->name === null) {
                continue;
            }

            $fqn = $trait->namespacedName?->toString() ?? $trait->name->toString();

            $results[$fqn] = new TraitData(
                fqn: $fqn,
                startPosition: $trait->getStartFilePos(),
                endPosition: $trait->getEndFilePos(),
            );
        }

        return $results;
    }
}

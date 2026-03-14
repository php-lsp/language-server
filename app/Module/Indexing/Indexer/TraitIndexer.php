<?php

declare(strict_types=1);

namespace App\Module\Indexing\Indexer;

use App\Core\Contracts\Indexing\AsIndexer;
use App\Module\Indexing\Storage\IndexData\TraitData;
use App\Module\PsiFile\PHPPsiFile;
use App\Module\PsiFile\Tree;
use PhpParser\Node\Stmt\Trait_;

#[AsIndexer]
/**
 * @extends AbstractPhpIndexer<TraitData>
 */
class TraitIndexer extends AbstractPhpIndexer
{
    public static function getKey(): string
    {
        return 'php.traits.fqn';
    }

    protected function indexInternal(PHPPsiFile $phpFile): array
    {
        $traits = Tree::childrenOfType($phpFile->ast, Trait_::class);

        $results = [];
        foreach ($traits as $trait) {
            $fqn = $trait->namespacedName->toString();

            $results[$fqn] = new TraitData(
                fqn: $fqn,
                startPosition: $trait->getStartFilePos(),
                endPosition: $trait->getEndFilePos(),
            );
        }

        return $results;
    }
}

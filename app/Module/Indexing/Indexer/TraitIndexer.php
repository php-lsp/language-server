<?php
declare(strict_types=1);

namespace App\Module\Indexing\Indexer;

use App\Core\Contracts\Indexing\AsIndexer;
use App\Module\PsiFile\PHPPsiFile;
use App\Module\PsiFile\Tree;
use PhpParser\Node\Stmt\Trait_;

#[AsIndexer]
/**
 * @implements \App\Core\Contracts\Indexing\IndexerInterface<string>
 */
class TraitIndexer extends AbstractPhpIndexer
{
    public static function getKey(): string
    {
        return 'php.traits.fqn';
    }

    protected function indexInternal(PHPPsiFile $phpFile): array
    {
        $interfaces = Tree::childrenOfType($phpFile->ast, Trait_::class);

        $results = [];
        foreach ($interfaces as $interface) {
            $results[] = $interface->namespacedName->toString();
        }

        return $results;
    }
}

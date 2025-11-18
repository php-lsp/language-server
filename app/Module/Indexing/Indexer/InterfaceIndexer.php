<?php

namespace App\Module\Indexing\Indexer;

use App\Core\Contracts\Indexing\AsIndexer;
use App\Module\Indexing\IndexerInterface;
use App\Module\PsiFile\PHPPsiFile;
use App\Module\PsiFile\Tree;
use PhpParser\Node\Stmt\Interface_;

#[AsIndexer]
/**
 * @implements IndexerInterface<string>
 */
class InterfaceIndexer extends AbstractPhpIndexer
{
    public static function getKey(): string
    {
        return 'php.interfaces.fqn';
    }

    protected function indexInternal(PHPPsiFile $phpFile): array
    {
        $classes = Tree::childrenOfType($phpFile->ast, Interface_::class);

        return array_map(
            fn(Interface_ $interface) => $interface->namespacedName->toString(),
            $classes,
        );
    }
}

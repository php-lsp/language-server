<?php

namespace App\Module\Indexing\Indexer;

use App\Core\Contracts\Indexing\AsIndexer;
use App\Core\Contracts\Indexing\IndexerInterface;
use App\Module\PsiFile\PHPPsiFile;
use App\Module\PsiFile\Tree;
use PhpParser\Node\Stmt\Interface_;

#[AsIndexer]
/**
 * @implements \App\Core\Contracts\Indexing\IndexerInterface<string>
 */
class InterfaceIndexer extends AbstractPhpIndexer
{
    public static function getKey(): string
    {
        return 'php.interfaces.fqn';
    }

    protected function indexInternal(PHPPsiFile $phpFile): array
    {
        $interfaces = Tree::childrenOfType($phpFile->ast, Interface_::class);

        $results = [];
        foreach ($interfaces as $interface) {
            $results[] = $interface->namespacedName->toString();
        }

        return $results;
    }
}

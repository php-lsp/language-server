<?php

declare(strict_types=1);

namespace App\Module\Indexing\Indexer;

use App\Core\Contracts\Indexing\AsIndexer;
use App\Module\Indexing\Storage\IndexData\InterfaceData;
use App\Module\PsiFile\PHPPsiFile;
use App\Module\PsiFile\Tree;
use PhpParser\Node\Stmt\Interface_;

#[AsIndexer]
/**
 * @extends AbstractPhpIndexer<InterfaceData>
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
            $fqn = $interface->namespacedName->toString();

            $extends = [];
            foreach ($interface->extends as $extend) {
                $extends[] = $extend->toString();
            }

            $results[$fqn] = new InterfaceData(
                fqn: $fqn,
                startPosition: $interface->getStartFilePos(),
                endPosition: $interface->getEndFilePos(),
                extends: $extends,
            );
        }

        return $results;
    }
}

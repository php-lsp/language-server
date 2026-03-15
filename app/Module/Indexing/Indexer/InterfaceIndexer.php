<?php

declare(strict_types=1);

namespace App\Module\Indexing\Indexer;

use App\Core\Contracts\Indexing\AsIndexer;
use App\Module\Indexing\Data\InterfaceData;
use App\Module\PsiFile\PHPPsiFile;
use App\Module\PsiFile\Tree;
use Override;
use PhpParser\Node\Stmt\Interface_;

/**
 * @extends AbstractPhpIndexer<InterfaceData>
 */
#[AsIndexer]
class InterfaceIndexer extends AbstractPhpIndexer
{
    #[Override]
    public static function getKey(): string
    {
        return 'php.interfaces.fqn';
    }

    #[Override]
    protected function indexInternal(PHPPsiFile $phpFile): array
    {
        $interfaces = Tree::childrenOfType($phpFile->ast, Interface_::class);

        $results = [];
        foreach ($interfaces as $interface) {
            if ($interface->name === null) {
                continue;
            }

            $fqn = $interface->namespacedName?->toString() ?? $interface->name->toString();

            $extends = [];
            foreach ($interface->extends as $ext) {
                $extends[] = $ext->toString();
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

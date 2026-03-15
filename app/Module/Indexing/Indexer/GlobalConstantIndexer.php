<?php

declare(strict_types=1);

namespace App\Module\Indexing\Indexer;

use App\Core\Contracts\Indexing\AsIndexer;
use App\Module\Indexing\Data\ConstantData;
use App\Module\PsiFile\PHPPsiFile;
use App\Module\PsiFile\Tree;
use Override;
use PhpParser\Node\Stmt\Const_;

/**
 * @extends AbstractPhpIndexer<ConstantData>
 */
#[AsIndexer]
class GlobalConstantIndexer extends AbstractPhpIndexer
{
    #[Override]
    public static function getKey(): string
    {
        return 'php.constants.fqn';
    }

    #[Override]
    protected function indexInternal(PHPPsiFile $phpFile): array
    {
        $constStmts = Tree::childrenOfType($phpFile->ast, Const_::class);

        $results = [];
        foreach ($constStmts as $constStmt) {
            foreach ($constStmt->consts as $const) {
                $fqn = $const->namespacedName?->toString() ?? $const->name->toString();

                $results[$fqn] = new ConstantData(
                    name: $fqn,
                    className: null,
                    startPosition: $constStmt->getStartFilePos(),
                    endPosition: $constStmt->getEndFilePos(),
                    type: null,
                    visibility: null,
                );
            }
        }

        return $results;
    }
}

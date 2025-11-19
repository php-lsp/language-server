<?php

namespace App\Module\Indexing\Indexer;

use App\Core\Contracts\Indexing\AsIndexer;
use App\Module\PsiFile\PHPPsiFile;
use App\Module\PsiFile\Tree;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\Function_;

#[AsIndexer]
/**
 * @implements AbstractPhpIndexer<string>
 */
class FunctionIndexer extends AbstractPhpIndexer
{
    public static function getKey(): string
    {
        return 'php.functions.fqn';
    }

    protected function indexInternal(PHPPsiFile $phpFile): array
    {
        $functions = Tree::childrenOfType($phpFile->ast, Function_::class);

        $results = [];
        foreach ($functions as $function) {
            $results[] = $function->namespacedName->toString();
        }

        return $results;
    }
}

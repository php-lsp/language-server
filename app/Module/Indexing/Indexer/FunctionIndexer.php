<?php

namespace App\Module\Indexing\Indexer;

use App\Core\Contracts\Indexing\AsIndexer;
use App\Module\PsiFile\PHPPsiFile;
use App\Module\PsiFile\Tree;
use PhpParser\Node\Stmt\Function_;

#[AsIndexer]
/**
 * @implements AbstractPhpIndexer<list<string, int>>
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
            $functionName = $function->namespacedName->toString();
            // todo: using name as a keys isn't correct, only debug purposes
            $results[$functionName] = [$functionName, $function->getStartFilePos()];
        }

        return $results;
    }
}

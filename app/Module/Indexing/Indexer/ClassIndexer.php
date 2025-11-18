<?php

namespace App\Module\Indexing\Indexer;

use App\Core\Contracts\Indexing\AsIndexer;
use App\Module\PsiFile\PHPPsiFile;
use App\Module\PsiFile\Tree;
use PhpParser\Node\Stmt\Class_;

#[AsIndexer]
/**
 * @implements AbstractPhpIndexer<string>
 */
class ClassIndexer extends AbstractPhpIndexer
{
    public static function getKey(): string
    {
        return 'php.classes.fqn';
    }

    protected function indexInternal(PHPPsiFile $phpFile): array
    {
        $classes = Tree::childrenOfType($phpFile->ast, Class_::class);

        return array_map(
            fn(Class_ $class) => $class->namespacedName->toString(),
            $classes,
        );
    }
}

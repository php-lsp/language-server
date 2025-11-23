<?php
declare(strict_types=1);

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

        $results = [];
        foreach ($classes as $class) {
            $className = $class->namespacedName->toString();
            // todo: using name as a keys isn't correct, only debug purposes
            $results[$className] = $className;
        }

        return $results;
    }
}

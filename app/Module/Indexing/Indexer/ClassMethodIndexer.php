<?php
declare(strict_types=1);

namespace App\Module\Indexing\Indexer;

use App\Core\Contracts\Indexing\AsIndexer;
use App\Module\PsiFile\PHPPsiFile;
use App\Module\PsiFile\Tree;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\ClassMethod;

#[AsIndexer]
/**
 * @implements AbstractPhpIndexer<array<string, list<string>>
 */
class ClassMethodIndexer extends AbstractPhpIndexer
{
    public static function getKey(): string
    {
        return 'php.classMethods.fqn';
    }

    protected function indexInternal(PHPPsiFile $phpFile): array
    {
        $classes = Tree::childrenOfType($phpFile->ast, Class_::class);

        $results = [];
        foreach ($classes as $class) {
            $methods = Tree::childrenOfType($class, ClassMethod::class);

            $className = $class->namespacedName->toString();
            foreach ($methods as $method) {
                $results[$className][] = $method->name->toString();
            }
        }

        return $results;
    }
}

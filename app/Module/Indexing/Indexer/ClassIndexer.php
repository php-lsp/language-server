<?php

declare(strict_types=1);

namespace App\Module\Indexing\Indexer;

use App\Core\Contracts\Indexing\AsIndexer;
use App\Module\Indexing\Storage\IndexData\ClassData;
use App\Module\PsiFile\PHPPsiFile;
use App\Module\PsiFile\Tree;
use PhpParser\Node\Stmt\Class_;

#[AsIndexer]
/**
 * @extends AbstractPhpIndexer<ClassData>
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

            $extends = null;
            if ($class->extends !== null) {
                $extends = $class->extends->toString();
            }

            $implements = [];
            foreach ($class->implements as $implement) {
                $implements[] = $implement->toString();
            }

            $results[$className] = new ClassData(
                fqn: $className,
                startPosition: $class->getStartFilePos(),
                endPosition: $class->getEndFilePos(),
                extends: $extends,
                implements: $implements,
                isAbstract: $class->isAbstract(),
                isFinal: $class->isFinal(),
            );
        }

        return $results;
    }
}

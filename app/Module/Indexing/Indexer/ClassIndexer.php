<?php

declare(strict_types=1);

namespace App\Module\Indexing\Indexer;

use App\Core\Contracts\Indexing\AsIndexer;
use App\Module\Indexing\Data\ClassData;
use App\Module\PsiFile\PHPPsiFile;
use App\Module\PsiFile\Tree;
use PhpParser\Node\Stmt\Class_;

/**
 * @extends AbstractPhpIndexer<ClassData>
 */
#[AsIndexer]
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
            if ($class->name === null) {
                continue;
            }

            $fqn = $class->namespacedName?->toString() ?? $class->name->toString();

            $implements = [];
            foreach ($class->implements as $impl) {
                $implements[] = $impl->toString();
            }

            $results[$fqn] = new ClassData(
                fqn: $fqn,
                startPosition: $class->getStartFilePos(),
                endPosition: $class->getEndFilePos(),
                isAbstract: $class->isAbstract(),
                isFinal: $class->isFinal(),
                isReadonly: $class->isReadonly(),
                extends: $class->extends?->toString(),
                implements: $implements,
            );
        }

        return $results;
    }
}

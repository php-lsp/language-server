<?php

declare(strict_types=1);

namespace App\Module\Indexing\Indexer;

use App\Core\Contracts\Indexing\AsIndexer;
use App\Module\Indexing\Data\MethodData;
use App\Module\Indexing\Data\NodeTypeExtractor;
use App\Module\PsiFile\PHPPsiFile;
use App\Module\PsiFile\Tree;
use Override;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Interface_;
use PhpParser\Node\Stmt\Trait_;

/**
 * @extends AbstractPhpIndexer<list<MethodData>>
 */
#[AsIndexer]
class ClassMethodIndexer extends AbstractPhpIndexer
{
    #[Override]
    public static function getKey(): string
    {
        return 'php.classMethods.fqn';
    }

    #[Override]
    protected function indexInternal(PHPPsiFile $phpFile): array
    {
        $classLikes = Tree::childrenOfTypes($phpFile->ast, Class_::class, Interface_::class, Trait_::class);

        $results = [];
        foreach ($classLikes as $classLike) {
            if ($classLike->name === null) {
                continue;
            }

            $className = $classLike->namespacedName?->toString() ?? $classLike->name->toString();
            $methods = Tree::childrenOfType($classLike, ClassMethod::class);

            $methodDataList = [];
            foreach ($methods as $method) {
                $methodDataList[] = new MethodData(
                    name: $method->name->toString(),
                    className: $className,
                    startPosition: $method->getStartFilePos(),
                    endPosition: $method->getEndFilePos(),
                    visibility: NodeTypeExtractor::extractVisibility($method),
                    isStatic: $method->isStatic(),
                    isAbstract: $method->isAbstract(),
                    returnType: NodeTypeExtractor::typeToString($method->returnType),
                    parameters: NodeTypeExtractor::extractParameters($method),
                );
            }

            if ($methodDataList !== []) {
                $results[$className] = $methodDataList;
            }
        }

        return $results;
    }
}

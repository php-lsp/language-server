<?php

declare(strict_types=1);

namespace App\Module\Indexing\Indexer;

use App\Core\Contracts\Indexing\AsIndexer;
use App\Module\Indexing\Data\ConstantData;
use App\Module\Indexing\Data\NodeTypeExtractor;
use App\Module\PsiFile\PHPPsiFile;
use App\Module\PsiFile\Tree;
use Override;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\ClassConst;
use PhpParser\Node\Stmt\Enum_;
use PhpParser\Node\Stmt\Interface_;
use PhpParser\Node\Stmt\Trait_;

/**
 * @extends AbstractPhpIndexer<ConstantData>
 */
#[AsIndexer]
class ClassConstantIndexer extends AbstractPhpIndexer
{
    #[Override]
    public static function getKey(): string
    {
        return 'php.classConstants.fqn';
    }

    #[Override]
    protected function indexInternal(PHPPsiFile $phpFile): array
    {
        $classLikes = Tree::childrenOfTypes(
            $phpFile->ast,
            Class_::class,
            Interface_::class,
            Trait_::class,
            Enum_::class,
        );

        $results = [];
        foreach ($classLikes as $classLike) {
            if ($classLike->name === null) {
                continue;
            }

            $className = $classLike->namespacedName?->toString() ?? $classLike->name->toString();
            foreach ($this->extractConstants($classLike, $className) as $constant) {
                $results[$className . '::' . $constant->name] = $constant;
            }
        }

        return $results;
    }

    /**
     * @return list<ConstantData>
     */
    private function extractConstants(Class_|Interface_|Trait_|Enum_ $classLike, string $className): array
    {
        $constants = [];

        foreach ($classLike->stmts as $stmt) {
            if (!$stmt instanceof ClassConst) {
                continue;
            }

            foreach ($stmt->consts as $const) {
                $constants[] = new ConstantData(
                    name: $const->name->toString(),
                    className: $className,
                    startPosition: $stmt->getStartFilePos(),
                    endPosition: $stmt->getEndFilePos(),
                    type: NodeTypeExtractor::typeToString($stmt->type),
                    visibility: NodeTypeExtractor::extractVisibility($stmt),
                );
            }
        }

        return $constants;
    }
}

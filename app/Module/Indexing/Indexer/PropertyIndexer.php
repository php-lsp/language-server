<?php

declare(strict_types=1);

namespace App\Module\Indexing\Indexer;

use App\Core\Contracts\Indexing\AsIndexer;
use App\Module\Indexing\Data\NodeTypeExtractor;
use App\Module\Indexing\Data\PropertyData;
use App\Module\Indexing\Data\Visibility;
use App\Module\PsiFile\PHPPsiFile;
use App\Module\PsiFile\Tree;
use Override;
use PhpParser\Node\Param;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Property;
use PhpParser\Node\Stmt\Trait_;

/**
 * @extends AbstractPhpIndexer<PropertyData>
 */
#[AsIndexer]
class PropertyIndexer extends AbstractPhpIndexer
{
    #[Override]
    public static function getKey(): string
    {
        return 'php.properties.fqn';
    }

    #[Override]
    protected function indexInternal(PHPPsiFile $phpFile): array
    {
        $classLikes = Tree::childrenOfTypes($phpFile->ast, Class_::class, Trait_::class);

        $results = [];
        foreach ($classLikes as $classLike) {
            if ($classLike->name === null) {
                continue;
            }

            $className = $classLike->namespacedName?->toString() ?? $classLike->name->toString();
            foreach ($this->extractProperties($classLike, $className) as $property) {
                $results[$className . '::$' . $property->name] = $property;
            }
        }

        return $results;
    }

    /**
     * @return list<PropertyData>
     */
    private function extractProperties(Class_|Trait_ $classLike, string $className): array
    {
        $properties = [];

        foreach ($classLike->stmts as $stmt) {
            if ($stmt instanceof Property) {
                foreach ($stmt->props as $prop) {
                    $properties[] = new PropertyData(
                        name: $prop->name->toString(),
                        className: $className,
                        startPosition: $stmt->getStartFilePos(),
                        endPosition: $stmt->getEndFilePos(),
                        visibility: NodeTypeExtractor::extractVisibility($stmt),
                        type: NodeTypeExtractor::typeToString($stmt->type),
                        isStatic: $stmt->isStatic(),
                        isReadonly: $stmt->isReadonly(),
                        isPromoted: false,
                    );
                }
            }

            if ($stmt instanceof ClassMethod && $stmt->name->toString() === '__construct') {
                foreach ($stmt->params as $param) {
                    if ($param->flags === 0) {
                        continue;
                    }

                    $varName = $param->var instanceof \PhpParser\Node\Expr\Variable && is_string($param->var->name)
                        ? $param->var->name
                        : '';

                    $properties[] = new PropertyData(
                        name: $varName,
                        className: $className,
                        startPosition: $param->getStartFilePos(),
                        endPosition: $param->getEndFilePos(),
                        visibility: $this->paramVisibility($param),
                        type: NodeTypeExtractor::typeToString($param->type),
                        isStatic: false,
                        isReadonly: (bool) ($param->flags & \PhpParser\Modifiers::READONLY),
                        isPromoted: true,
                    );
                }
            }
        }

        return $properties;
    }

    private function paramVisibility(Param $param): Visibility
    {
        if (($param->flags & \PhpParser\Modifiers::PRIVATE) !== 0) {
            return Visibility::Private;
        }

        if (($param->flags & \PhpParser\Modifiers::PROTECTED) !== 0) {
            return Visibility::Protected;
        }

        return Visibility::Public;
    }
}

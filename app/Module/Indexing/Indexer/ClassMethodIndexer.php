<?php

declare(strict_types=1);

namespace App\Module\Indexing\Indexer;

use App\Core\Contracts\Indexing\AsIndexer;
use App\Module\Indexing\Storage\IndexData\MethodData;
use App\Module\Indexing\Storage\IndexData\ParameterData;
use App\Module\PsiFile\PHPPsiFile;
use App\Module\PsiFile\Tree;
use PhpParser\Node;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Enum_;
use PhpParser\Node\Stmt\Interface_;
use PhpParser\Node\Stmt\Trait_;

#[AsIndexer]
/**
 * @extends AbstractPhpIndexer<MethodData>
 */
class ClassMethodIndexer extends AbstractPhpIndexer
{
    public static function getKey(): string
    {
        return 'php.classMethods.fqn';
    }

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
            $className = $classLike->namespacedName?->toString() ?? '';
            $methods = Tree::childrenOfType($classLike, ClassMethod::class);

            foreach ($methods as $method) {
                $methodName = $method->name->toString();
                $key = $className . '::' . $methodName;

                $visibility = 'public';
                if ($method->isPrivate()) {
                    $visibility = 'private';
                } elseif ($method->isProtected()) {
                    $visibility = 'protected';
                }

                $parameters = [];
                foreach ($method->params as $param) {
                    $parameters[] = new ParameterData(
                        name: $param->var instanceof Node\Expr\Variable ? (string) $param->var->name : '',
                        type: $this->extractType($param->type),
                        hasDefault: $param->default !== null,
                        isVariadic: $param->variadic,
                        isPromoted: ($param->flags & Class_::MODIFIER_PUBLIC) !== 0
                        || ($param->flags & Class_::MODIFIER_PROTECTED) !== 0
                        || ($param->flags & Class_::MODIFIER_PRIVATE) !== 0,
                    );
                }

                $results[$key] = new MethodData(
                    name: $methodName,
                    className: $className,
                    startPosition: $method->getStartFilePos(),
                    endPosition: $method->getEndFilePos(),
                    visibility: $visibility,
                    isStatic: $method->isStatic(),
                    isAbstract: $method->isAbstract(),
                    parameters: $parameters,
                    returnType: $this->extractType($method->returnType),
                );
            }
        }

        return $results;
    }

    private function extractType(?Node $type): ?string
    {
        if ($type === null) {
            return null;
        }
        if ($type instanceof Node\Name) {
            return $type->toString();
        }
        if ($type instanceof Node\Identifier) {
            return $type->toString();
        }
        if ($type instanceof Node\NullableType) {
            return '?' . $this->extractType($type->type);
        }
        if ($type instanceof Node\UnionType) {
            return implode('|', array_map(fn(Node $t) => $this->extractType($t), $type->types));
        }
        if ($type instanceof Node\IntersectionType) {
            return implode('&', array_map(fn(Node $t) => $this->extractType($t), $type->types));
        }

        return null;
    }
}

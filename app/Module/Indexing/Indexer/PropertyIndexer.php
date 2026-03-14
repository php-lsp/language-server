<?php

declare(strict_types=1);

namespace App\Module\Indexing\Indexer;

use App\Core\Contracts\Indexing\AsIndexer;
use App\Module\Indexing\Storage\IndexData\PropertyData;
use App\Module\PsiFile\PHPPsiFile;
use App\Module\PsiFile\Tree;
use PhpParser\Node;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\Property;
use PhpParser\Node\Stmt\Trait_;

#[AsIndexer]
/**
 * @extends AbstractPhpIndexer<PropertyData>
 */
class PropertyIndexer extends AbstractPhpIndexer
{
    public static function getKey(): string
    {
        return 'php.properties.fqn';
    }

    protected function indexInternal(PHPPsiFile $phpFile): array
    {
        $classLikes = Tree::childrenOfTypes($phpFile->ast, Class_::class, Trait_::class);

        $results = [];
        foreach ($classLikes as $classLike) {
            $className = $classLike->namespacedName?->toString() ?? '';
            $properties = Tree::childrenOfType($classLike, Property::class);

            foreach ($properties as $property) {
                foreach ($property->props as $prop) {
                    $name = $prop->name->toString();
                    $key = $className . '::$' . $name;

                    $visibility = 'public';
                    if ($property->isPrivate()) {
                        $visibility = 'private';
                    } elseif ($property->isProtected()) {
                        $visibility = 'protected';
                    }

                    $results[$key] = new PropertyData(
                        name: $name,
                        className: $className,
                        startPosition: $property->getStartFilePos(),
                        endPosition: $property->getEndFilePos(),
                        visibility: $visibility,
                        type: $this->extractType($property->type),
                        isStatic: $property->isStatic(),
                        isReadonly: $property->isReadonly(),
                    );
                }
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

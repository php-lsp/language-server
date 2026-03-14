<?php

declare(strict_types=1);

namespace App\Module\Indexing\Indexer;

use App\Core\Contracts\Indexing\AsIndexer;
use App\Module\Indexing\Storage\IndexData\ConstantData;
use App\Module\PsiFile\PHPPsiFile;
use App\Module\PsiFile\Tree;
use PhpParser\Node;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\ClassConst;
use PhpParser\Node\Stmt\Enum_;
use PhpParser\Node\Stmt\Interface_;

#[AsIndexer]
/**
 * @extends AbstractPhpIndexer<ConstantData>
 */
class ClassConstantIndexer extends AbstractPhpIndexer
{
    public static function getKey(): string
    {
        return 'php.classConstants.fqn';
    }

    protected function indexInternal(PHPPsiFile $phpFile): array
    {
        $classLikes = Tree::childrenOfTypes($phpFile->ast, Class_::class, Interface_::class, Enum_::class);

        $results = [];
        foreach ($classLikes as $classLike) {
            $className = $classLike->namespacedName?->toString() ?? '';
            $constants = Tree::childrenOfType($classLike, ClassConst::class);

            foreach ($constants as $classConst) {
                foreach ($classConst->consts as $const) {
                    $name = $const->name->toString();
                    $key = $className . '::' . $name;

                    $value = null;
                    if ($const->value instanceof Node\Scalar\String_) {
                        $value = $const->value->value;
                    } elseif ($const->value instanceof Node\Scalar\Int_) {
                        $value = (string) $const->value->value;
                    } elseif ($const->value instanceof Node\Scalar\Float_) {
                        $value = (string) $const->value->value;
                    }

                    $results[$key] = new ConstantData(
                        name: $name,
                        ownerFqn: $className,
                        startPosition: $classConst->getStartFilePos(),
                        endPosition: $classConst->getEndFilePos(),
                        type: $this->extractType($classConst->type),
                        value: $value,
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

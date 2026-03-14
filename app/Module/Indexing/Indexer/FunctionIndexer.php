<?php

declare(strict_types=1);

namespace App\Module\Indexing\Indexer;

use App\Core\Contracts\Indexing\AsIndexer;
use App\Module\Indexing\Storage\IndexData\FunctionData;
use App\Module\Indexing\Storage\IndexData\ParameterData;
use App\Module\PsiFile\PHPPsiFile;
use App\Module\PsiFile\Tree;
use PhpParser\Node;
use PhpParser\Node\Stmt\Function_;

#[AsIndexer]
/**
 * @extends AbstractPhpIndexer<FunctionData>
 */
class FunctionIndexer extends AbstractPhpIndexer
{
    public static function getKey(): string
    {
        return 'php.functions.fqn';
    }

    protected function indexInternal(PHPPsiFile $phpFile): array
    {
        $functions = Tree::childrenOfType($phpFile->ast, Function_::class);

        $results = [];
        foreach ($functions as $function) {
            $functionName = $function->namespacedName->toString();

            $parameters = [];
            foreach ($function->params as $param) {
                $parameters[] = new ParameterData(
                    name: $param->var instanceof Node\Expr\Variable ? (string) $param->var->name : '',
                    type: $this->extractType($param->type),
                    hasDefault: $param->default !== null,
                    isVariadic: $param->variadic,
                    isPromoted: false,
                );
            }

            $results[$functionName] = new FunctionData(
                fqn: $functionName,
                startPosition: $function->getStartFilePos(),
                endPosition: $function->getEndFilePos(),
                parameters: $parameters,
                returnType: $this->extractType($function->returnType),
            );
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

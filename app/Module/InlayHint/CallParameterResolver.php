<?php

declare(strict_types=1);

namespace App\Module\InlayHint;

use App\Module\Indexing\Data\FunctionData;
use App\Module\Indexing\Data\MethodData;
use App\Module\Indexing\Data\ParameterData;
use App\Module\Indexing\Indexer\ClassMethodIndexer;
use App\Module\Indexing\Indexer\FunctionIndexer;
use App\Module\Indexing\IndexLookup;
use App\Module\PsiFile\Tree;
use PhpParser\Node;

final class CallParameterResolver
{
    public function __construct(
        private readonly IndexLookup $indexLookup,
    ) {}

    /**
     * Resolve parameter definitions for a call-like node.
     *
     * @return list<ParameterData>|null
     */
    public function resolve(Node $callNode): ?array
    {
        return match (true) {
            $callNode instanceof Node\Expr\FuncCall => $this->resolveFuncCall($callNode),
            $callNode instanceof Node\Expr\MethodCall => $this->resolveMethodCall($callNode),
            $callNode instanceof Node\Expr\StaticCall => $this->resolveStaticCall($callNode),
            $callNode instanceof Node\Expr\New_ => $this->resolveNew($callNode),
            default => null,
        };
    }

    /**
     * @return list<ParameterData>|null
     */
    private function resolveFuncCall(Node\Expr\FuncCall $funcCall): ?array
    {
        if (!$funcCall->name instanceof Node\Name) {
            return null;
        }

        $functionName = $funcCall->name->toString();

        foreach ($this->indexLookup->findByKey(FunctionIndexer::class) as $entry) {
            /** @var FunctionData $data */
            $data = $entry->value;

            if ($data->fqn === $functionName) {
                return $data->parameters;
            }
        }

        return null;
    }

    /**
     * @return list<ParameterData>|null
     */
    private function resolveMethodCall(Node\Expr\MethodCall $methodCall): ?array
    {
        $name = $methodCall->name;
        if (!$name instanceof Node\Identifier) {
            return null;
        }

        $className = self::resolveVariableClass($methodCall->var);

        return $className !== null ? $this->findMethodParameters($className, $name->toString()) : null;
    }

    /**
     * @return list<ParameterData>|null
     */
    private function resolveStaticCall(Node\Expr\StaticCall $staticCall): ?array
    {
        if (!$staticCall->class instanceof Node\Name || !$staticCall->name instanceof Node\Identifier) {
            return null;
        }

        return $this->findMethodParameters($staticCall->class->toString(), $staticCall->name->toString());
    }

    /**
     * @return list<ParameterData>|null
     */
    private function resolveNew(Node\Expr\New_ $newExpr): ?array
    {
        return $newExpr->class instanceof Node\Name
            ? $this->findMethodParameters($newExpr->class->toString(), '__construct')
            : null;
    }

    /**
     * @return list<ParameterData>|null
     */
    private function findMethodParameters(string $className, string $methodName): ?array
    {
        $entry = $this->indexLookup->findEntry(ClassMethodIndexer::class, $className . '::' . $methodName);
        if ($entry === null) {
            return null;
        }

        /** @var MethodData $data */
        $data = $entry->value;

        return $data->parameters;
    }

    private static function resolveVariableClass(Node\Expr $expr): ?string
    {
        if ($expr instanceof Node\Expr\Variable && $expr->name === 'this') {
            $class = Tree::parentOfType($expr, Node\Stmt\Class_::class);

            return $class?->namespacedName?->toString() ?? $class?->name?->toString();
        }

        if ($expr instanceof Node\Expr\New_ && $expr->class instanceof Node\Name) {
            return $expr->class->toString();
        }

        return null;
    }
}

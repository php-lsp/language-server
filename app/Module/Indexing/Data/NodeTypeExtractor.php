<?php

declare(strict_types=1);

namespace App\Module\Indexing\Data;

use PhpParser\Node;

final class NodeTypeExtractor
{
    public static function typeToString(?Node $type): ?string
    {
        if ($type === null) {
            return null;
        }

        if ($type instanceof Node\NullableType) {
            return '?' . self::typeToString($type->type);
        }

        if ($type instanceof Node\UnionType) {
            return implode('|', array_map(
                static fn(Node $t): string => self::typeToString($t) ?? 'mixed',
                $type->types,
            ));
        }

        if ($type instanceof Node\IntersectionType) {
            return implode('&', array_map(
                static fn(Node $t): string => self::typeToString($t) ?? 'mixed',
                $type->types,
            ));
        }

        if ($type instanceof Node\Identifier) {
            return $type->toString();
        }

        if ($type instanceof Node\Name) {
            return $type->toString();
        }

        return null;
    }

    public static function extractVisibility(Node\Stmt\ClassMethod|Node\Stmt\Property|Node\Stmt\ClassConst $node): Visibility
    {
        if ($node->isPrivate()) {
            return Visibility::Private;
        }

        if ($node->isProtected()) {
            return Visibility::Protected;
        }

        return Visibility::Public;
    }

    /**
     * @return list<ParameterData>
     */
    public static function extractParameters(Node\FunctionLike $node): array
    {
        $parameters = [];

        foreach ($node->getParams() as $param) {
            $varName = $param->var instanceof Node\Expr\Variable && is_string($param->var->name)
                ? $param->var->name
                : '';

            $parameters[] = new ParameterData(
                name: '$' . $varName,
                type: self::typeToString($param->type),
                hasDefault: $param->default !== null,
                isVariadic: $param->variadic,
                isPromoted: $param->flags !== 0,
                isNullable: $param->type instanceof Node\NullableType,
            );
        }

        return $parameters;
    }
}

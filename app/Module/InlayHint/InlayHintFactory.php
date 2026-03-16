<?php

declare(strict_types=1);

namespace App\Module\InlayHint;

use App\Module\Indexing\Data\ParameterData;
use App\Module\PsiFile\Tree;
use Lsp\Extension\DocumentManager\Editor\Document\Document;
use Lsp\Protocol\Type\InlayHint;
use Lsp\Protocol\Type\InlayHintKind;
use Lsp\Protocol\Type\Position;
use PhpParser\Node;

final class InlayHintFactory
{
    /**
     * Build a parameter name hint for a function/method argument.
     *
     * Returns null when the hint should be suppressed (named arg, variadic, matching name).
     */
    public static function parameterHint(
        Node\Arg $arg,
        ?ParameterData $param,
        Document $document,
    ): ?InlayHint {
        if ($arg->name !== null || $param === null || $param->isVariadic) {
            return null;
        }

        if (self::argNameMatchesParam($arg, $param->name)) {
            return null;
        }

        [$line, $column] = Tree::toLineColumn($document, $arg->value->getStartFilePos());

        /** @var int<0, 2147483647> $line */
        /** @var int<0, 2147483647> $column */

        return new InlayHint(
            position: new Position($line, $column),
            label: $param->name . ':',
            kind: InlayHintKind::Parameter,
            paddingRight: true,
        );
    }

    private static function argNameMatchesParam(Node\Arg $arg, string $paramName): bool
    {
        return (
            $arg->value instanceof Node\Expr\Variable
            && is_string($arg->value->name)
            && ltrim($paramName, '$') === $arg->value->name
        );
    }
}

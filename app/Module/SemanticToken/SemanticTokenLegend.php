<?php

declare(strict_types=1);

namespace App\Module\SemanticToken;

use Lsp\Protocol\Type\SemanticTokenModifiers;
use Lsp\Protocol\Type\SemanticTokensLegend;
use Lsp\Protocol\Type\SemanticTokenTypes;

final class SemanticTokenLegend
{
    /** @var list<string> */
    public const array TOKEN_TYPES = [
        'namespace', // 0
        'type', // 1
        'class', // 2
        'enum', // 3
        'interface', // 4
        'typeParameter', // 5
        'parameter', // 6
        'variable', // 7
        'property', // 8
        'enumMember', // 9
        'function', // 10
        'method', // 11
        'keyword', // 12
        'modifier', // 13
        'comment', // 14
        'string', // 15
        'number', // 16
        'operator', // 17
        'decorator', // 18
    ];

    /** @var list<string> */
    public const array TOKEN_MODIFIERS = [
        'declaration', // bit 0
        'definition', // bit 1
        'readonly', // bit 2
        'static', // bit 3
        'deprecated', // bit 4
        'abstract', // bit 5
        'modification', // bit 6
        'documentation', // bit 7
        'defaultLibrary', // bit 8
    ];

    /**
     * @return int<0, 2147483647>
     */
    public static function typeIndex(SemanticTokenTypes $type): int
    {
        $index = array_search($type->value, self::TOKEN_TYPES, strict: true);
        if ($index === false) {
            return 0;
        }

        /** @var int<0, 2147483647> */
        return $index;
    }

    /**
     * @return int<0, 2147483647>
     */
    public static function modifierBit(SemanticTokenModifiers $modifier): int
    {
        $index = array_search($modifier->value, self::TOKEN_MODIFIERS, strict: true);
        if ($index === false) {
            return 0;
        }

        /** @var int<0, 2147483647> */
        return 1 << $index;
    }

    public static function legend(): SemanticTokensLegend
    {
        return new SemanticTokensLegend(
            tokenTypes: self::TOKEN_TYPES,
            tokenModifiers: self::TOKEN_MODIFIERS,
        );
    }
}

<?php

declare(strict_types=1);

namespace App\Module\SemanticToken;

readonly class RawSemanticToken
{
    /**
     * @param int<0, 2147483647> $line 0-based line number
     * @param int<0, 2147483647> $column 0-based start character
     * @param int<0, 2147483647> $length Token length in characters
     * @param int<0, 2147483647> $type Index into SemanticTokenLegend::TOKEN_TYPES
     * @param int<0, 2147483647> $modifiers Bitmask of SemanticTokenLegend::TOKEN_MODIFIERS
     */
    public function __construct(
        public int $line,
        public int $column,
        public int $length,
        public int $type,
        public int $modifiers = 0,
    ) {}
}

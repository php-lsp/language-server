<?php

declare(strict_types=1);

namespace App\Module\SemanticToken;

final class SemanticTokenEncoder
{
    /**
     * Encode raw semantic tokens into the LSP delta-encoded integer array.
     *
     * Tokens are sorted by (line, column) and encoded as groups of 5 integers:
     * [deltaLine, deltaStartChar, length, tokenType, tokenModifiers, ...]
     *
     * @param list<RawSemanticToken> $tokens
     *
     * @return list<int<0, 2147483647>>
     */
    public static function encode(array $tokens): array
    {
        if ($tokens === []) {
            return [];
        }

        usort(
            $tokens,
            static function (RawSemanticToken $a, RawSemanticToken $b): int {
                $lineCmp = $a->line <=> $b->line;

                return $lineCmp !== 0 ? $lineCmp : $a->column <=> $b->column;
            },
        );

        $result = [];
        $prevLine = 0;
        $prevColumn = 0;

        foreach ($tokens as $token) {
            $deltaLine = $token->line - $prevLine;
            $deltaColumn = $deltaLine === 0 ? $token->column - $prevColumn : $token->column;

            /** @var int<0, 2147483647> $deltaLine */
            /** @var int<0, 2147483647> $deltaColumn */
            $result[] = $deltaLine;
            $result[] = $deltaColumn;
            $result[] = $token->length;
            $result[] = $token->type;
            $result[] = $token->modifiers;

            $prevLine = $token->line;
            $prevColumn = $token->column;
        }

        return $result;
    }
}

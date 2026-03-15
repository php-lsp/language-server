<?php

declare(strict_types=1);

namespace App\Tests\Support;

use Lsp\Protocol\Type\CompletionItem;
use Lsp\Protocol\Type\Location;
use Lsp\Protocol\Type\Position;
use Lsp\Protocol\Type\Range;
use Lsp\Protocol\Type\SignatureInformation;
use Lsp\Protocol\Type\TextDocumentIdentifier;

final class ProtocolFactory
{
    public static function position(int $line = 0, int $character = 0): Position
    {
        return new Position($line, $character);
    }

    public static function textDocumentIdentifier(string $uri = 'file:///test.php'): TextDocumentIdentifier
    {
        return new TextDocumentIdentifier($uri);
    }

    public static function range(?Position $start = null, ?Position $end = null): Range
    {
        return new Range(
            $start ?? self::position(),
            $end ?? self::position(),
        );
    }

    public static function location(string $uri = 'file:///test.php', ?Range $range = null): Location
    {
        return new Location($uri, $range ?? self::range());
    }

    public static function completionItem(string $label = 'test'): CompletionItem
    {
        return new CompletionItem(label: $label);
    }

    public static function signatureInformation(string $label = 'test()'): SignatureInformation
    {
        return new SignatureInformation(label: $label);
    }
}

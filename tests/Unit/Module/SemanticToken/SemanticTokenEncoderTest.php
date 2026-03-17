<?php

declare(strict_types=1);

namespace App\Tests\Unit\Module\SemanticToken;

use App\Module\SemanticToken\RawSemanticToken;
use App\Module\SemanticToken\SemanticTokenEncoder;
use App\Tests\TestCase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\TestDox;

#[Group('unit')]
final class SemanticTokenEncoderTest extends TestCase
{
    #[TestDox('returns empty array for no tokens')]
    public function testEmptyTokens(): void
    {
        $this->assertSame([], SemanticTokenEncoder::encode([]));
    }

    #[TestDox('encodes single token with absolute position')]
    public function testSingleToken(): void
    {
        $tokens = [new RawSemanticToken(line: 2, column: 5, length: 3, type: 2, modifiers: 0)];

        $result = SemanticTokenEncoder::encode($tokens);

        $this->assertSame([2, 5, 3, 2, 0], $result);
    }

    #[TestDox('encodes two tokens on same line with delta column')]
    public function testSameLineDelta(): void
    {
        $tokens = [
            new RawSemanticToken(line: 2, column: 5, length: 3, type: 2, modifiers: 0),
            new RawSemanticToken(line: 2, column: 10, length: 4, type: 7, modifiers: 1),
        ];

        $result = SemanticTokenEncoder::encode($tokens);

        $this->assertSame([
            2, 5, 3, 2, 0,  // first: absolute
            0, 5, 4, 7, 1,  // second: deltaLine=0, deltaCol=10-5=5
        ], $result);
    }

    #[TestDox('encodes tokens on different lines with absolute column')]
    public function testDifferentLines(): void
    {
        $tokens = [
            new RawSemanticToken(line: 2, column: 5, length: 3, type: 2, modifiers: 0),
            new RawSemanticToken(line: 5, column: 2, length: 6, type: 10, modifiers: 0),
        ];

        $result = SemanticTokenEncoder::encode($tokens);

        $this->assertSame([
            2, 5, 3, 2, 0,   // first: absolute
            3, 2, 6, 10, 0,  // second: deltaLine=3, col=2 (absolute on new line)
        ], $result);
    }

    #[TestDox('sorts unsorted tokens before encoding')]
    public function testSortsTokens(): void
    {
        $tokens = [
            new RawSemanticToken(line: 5, column: 2, length: 6, type: 10, modifiers: 0),
            new RawSemanticToken(line: 2, column: 5, length: 3, type: 2, modifiers: 0),
        ];

        $result = SemanticTokenEncoder::encode($tokens);

        // Should be sorted: line 2 first, then line 5
        $this->assertSame([
            2, 5, 3, 2, 0,
            3, 2, 6, 10, 0,
        ], $result);
    }

    #[TestDox('handles tokens at line 0 column 0')]
    public function testOriginToken(): void
    {
        $tokens = [new RawSemanticToken(line: 0, column: 0, length: 5, type: 12, modifiers: 0)];

        $result = SemanticTokenEncoder::encode($tokens);

        $this->assertSame([0, 0, 5, 12, 0], $result);
    }

    #[TestDox('preserves modifier bitmask')]
    public function testModifierBitmask(): void
    {
        $tokens = [new RawSemanticToken(line: 1, column: 4, length: 3, type: 2, modifiers: 0b101)];

        $result = SemanticTokenEncoder::encode($tokens);

        $this->assertSame([1, 4, 3, 2, 5], $result);
    }
}

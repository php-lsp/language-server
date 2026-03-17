<?php

declare(strict_types=1);

namespace App\Tests\Playground;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\TestDox;

#[Group('e2e')]
#[TestDox('LSP Semantic Tokens')]
final class SemanticTokensTest extends PlaygroundTestCase
{
    protected static float $indexingWaitTime = 2.0;

    private static bool $filesOpened = false;

    protected function setUp(): void
    {
        parent::setUp();

        if (!self::$filesOpened) {
            self::openPlaygroundFile('src/Calculator.php');
            self::openPlaygroundFile('src/User.php');
            self::$filesOpened = true;
        }
    }

    #[TestDox('semanticTokens/full returns token data')]
    public function testSemanticTokensFullReturnsData(): void
    {
        $response = self::semanticTokensFull('src/Calculator.php');

        self::assertResponseOk($response);

        $result = $response['result'];
        self::assertIsArray($result);
        self::assertArrayHasKey('data', $result);
        self::assertIsArray($result['data']);
        self::assertNotEmpty($result['data'], 'Should return semantic token data');
    }

    #[TestDox('semantic token data is encoded as multiples of 5')]
    public function testSemanticTokenDataEncodedCorrectly(): void
    {
        $response = self::semanticTokensFull('src/Calculator.php');
        self::assertResponseOk($response);

        $data = $response['result']['data'];
        // LSP spec: data is encoded as groups of 5 integers:
        // [deltaLine, deltaStartChar, length, tokenType, tokenModifiers]
        self::assertSame(
            0,
            count($data) % 5,
            'Token data length should be a multiple of 5',
        );
    }

    #[TestDox('semantic tokens for smaller file returns data')]
    public function testSemanticTokensForUserFile(): void
    {
        $response = self::semanticTokensFull('src/User.php');

        self::assertResponseOk($response);

        $result = $response['result'];
        self::assertArrayHasKey('data', $result);
        self::assertNotEmpty($result['data']);
        self::assertSame(0, count($result['data']) % 5);
    }
}

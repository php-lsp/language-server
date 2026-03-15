<?php

declare(strict_types=1);

namespace App\Tests\Playground;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\TestDox;

#[Group('e2e')]
#[TestDox('LSP Signature Help')]
final class SignatureHelpTest extends PlaygroundTestCase
{
    protected static float $indexingWaitTime = 3.0;

    private static bool $filesOpened = false;

    protected function setUp(): void
    {
        parent::setUp();

        if (!self::$filesOpened) {
            self::openPlaygroundFile('src/Greeter.php');
            self::$filesOpened = true;
        }
    }

    #[TestDox('Signature help returns an error for unsupported type shape')]
    public function testSignatureHelpReturnsError(): void
    {
        // Greeter.php line 23: "\sprintf(...)"
        $response = self::signatureHelp('src/Greeter.php', 22, 24);

        self::assertJsonEquals([
            'error' => [
                'code' => 0,
                'message' => '@any',
                'data' => [],
            ],
        ], $response);
    }
}

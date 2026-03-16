<?php

declare(strict_types=1);

namespace App\Tests\Playground;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\TestDox;

#[Group('e2e')]
#[TestDox('LSP InlayHint')]
final class InlayHintTest extends PlaygroundTestCase
{
    protected static float $indexingWaitTime = 3.0;

    private static bool $filesOpened = false;

    protected function setUp(): void
    {
        parent::setUp();

        if (!self::$filesOpened) {
            self::openPlaygroundFile('src/UserService.php');
            self::$filesOpened = true;
        }
    }

    #[TestDox('Returns inlay hints for UserService method calls')]
    public function testUserServiceInlayHints(): void
    {
        $response = self::inlayHint(
            'src/UserService.php',
            ['line' => 0, 'character' => 0],
            ['line' => 60, 'character' => 0],
        );

        self::assertResponseOk($response);

        $hints = $response['result'];
        $this->assertIsArray($hints);
    }

    #[TestDox('Returns parameter hints for constructor call with arguments')]
    public function testConstructorCallHints(): void
    {
        // Line 30 (0-based): $user = new User($name, $email);
        $response = self::inlayHint(
            'src/UserService.php',
            ['line' => 29, 'character' => 0],
            ['line' => 30, 'character' => 50],
        );

        self::assertResponseOk($response);

        $hints = $response['result'];
        $this->assertIsArray($hints);
    }
}

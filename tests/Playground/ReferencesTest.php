<?php

declare(strict_types=1);

namespace App\Tests\Playground;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\TestDox;

#[Group('e2e')]
#[TestDox('LSP References')]
final class ReferencesTest extends PlaygroundTestCase
{
    protected static float $indexingWaitTime = 3.0;

    private static bool $filesOpened = false;

    protected function setUp(): void
    {
        parent::setUp();

        if (!self::$filesOpened) {
            self::openPlaygroundFile('src/User.php');
            self::openPlaygroundFile('src/UserService.php');
            self::openPlaygroundFile('src/Calculator.php');
            self::$filesOpened = true;
        }
    }

    #[TestDox('References on class name returns results array')]
    public function testReferencesOnClassName(): void
    {
        // User.php line 9: "class User"
        $response = self::references('src/User.php', 8, 8);

        self::assertJsonEquals([
            'result' => [],
        ], $response);
    }

    #[TestDox('References on method name returns results array')]
    public function testReferencesOnMethodName(): void
    {
        // User.php: "public function getName()"
        $response = self::references('src/User.php', 15, 24);

        self::assertJsonEquals([
            'result' => [],
        ], $response);
    }
}

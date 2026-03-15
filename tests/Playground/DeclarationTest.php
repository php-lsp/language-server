<?php

declare(strict_types=1);

namespace App\Tests\Playground;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\TestDox;

#[Group('e2e')]
#[TestDox('LSP Declaration')]
final class DeclarationTest extends PlaygroundTestCase
{
    protected static float $indexingWaitTime = 3.0;

    private static bool $filesOpened = false;

    protected function setUp(): void
    {
        parent::setUp();

        if (!self::$filesOpened) {
            self::openPlaygroundFile('src/UserService.php');
            self::openPlaygroundFile('src/User.php');
            self::openPlaygroundFile('src/Greeter.php');
            self::$filesOpened = true;
        }
    }

    #[TestDox('Declaration on class usage navigates to class definition file')]
    public function testDeclarationOnClassUsage(): void
    {
        // UserService.php line 30: "new User($name, $email)"
        $response = self::declaration('src/UserService.php', 29, 22);

        self::assertJsonEquals([
            'result' => [
                [
                    'uri' => self::playgroundFileUri('src/User.php'),
                    'range' => [
                        'start' => ['line' => 0, 'character' => 0],
                        'end' => ['line' => 0, 'character' => 0],
                    ],
                ],
            ],
        ], $response);
    }

    #[TestDox('Declaration on unresolvable position returns empty array')]
    public function testDeclarationOnEmptyPosition(): void
    {
        $response = self::declaration('src/UserService.php', 0, 0);

        self::assertJsonEquals([
            'result' => [],
        ], $response);
    }
}

<?php

declare(strict_types=1);

namespace App\Tests\Playground;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\TestDox;

#[Group('e2e')]
#[TestDox('LSP Implementation')]
final class ImplementationTest extends PlaygroundTestCase
{
    protected static float $indexingWaitTime = 3.0;

    private static bool $filesOpened = false;

    protected function setUp(): void
    {
        parent::setUp();

        if (!self::$filesOpened) {
            self::openPlaygroundFile('src/AppInterface.php');
            self::openPlaygroundFile('src/ConsoleApp.php');
            self::openPlaygroundFile('src/Calculator.php');
            self::$filesOpened = true;
        }
    }

    #[TestDox('implementation request returns valid response')]
    public function testImplementationReturnsValidResponse(): void
    {
        // AppInterface.php line 9 (0-indexed): "interface AppInterface"
        // "AppInterface" starts at char 10
        $response = self::implementation('src/AppInterface.php', 9, 15);

        self::assertResponseOk($response);

        $result = $response['result'];
        self::assertIsArray($result);

        // If implementations are found, verify structure
        if (!empty($result)) {
            $location = $result[0];
            self::assertArrayHasKey('uri', $location);
            self::assertArrayHasKey('range', $location);
        }
    }

    #[TestDox('implementation on non-interface returns empty')]
    public function testImplementationOnNonInterface(): void
    {
        // Calculator.php line 0: "<?php" — no symbol
        $response = self::implementation('src/Calculator.php', 0, 0);

        self::assertResponseOk($response);
        self::assertEmpty($response['result'], 'Should return empty for non-interface position');
    }

    #[TestDox('implementation on class name returns response')]
    public function testImplementationOnClassName(): void
    {
        // Calculator.php line 9 (0-indexed): "class Calculator"
        // "Calculator" starts at char 6
        $response = self::implementation('src/Calculator.php', 9, 8);

        self::assertResponseOk($response);
        self::assertIsArray($response['result']);
    }
}

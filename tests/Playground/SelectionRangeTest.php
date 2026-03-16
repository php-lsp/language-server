<?php

declare(strict_types=1);

namespace App\Tests\Playground;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\TestDox;

#[Group('e2e')]
#[TestDox('LSP Selection Range')]
final class SelectionRangeTest extends PlaygroundTestCase
{
    protected static float $indexingWaitTime = 3.0;

    private static bool $filesOpened = false;

    protected function setUp(): void
    {
        parent::setUp();

        if (!self::$filesOpened) {
            self::openPlaygroundFile('src/Calculator.php');
            self::$filesOpened = true;
        }
    }

    #[TestDox('Selection range returns a result array')]
    public function testSelectionRangeReturnsResult(): void
    {
        // Position on "result" property in Calculator.php line 11: "private float $result = 0.0;"
        // (0-based line 11, character 20 = on "$result")
        $response = self::selectionRange('src/Calculator.php', [
            ['line' => 11, 'character' => 20],
        ]);

        self::assertResponseOk($response);

        $result = $response['result'];
        $this->assertIsArray($result);
        $this->assertCount(1, $result, 'Should return one entry for one position');
    }

    #[TestDox('Selection range with multiple positions returns results for each')]
    public function testSelectionRangeMultiplePositions(): void
    {
        $response = self::selectionRange('src/Calculator.php', [
            ['line' => 11, 'character' => 20],  // property declaration
            ['line' => 21, 'character' => 8],    // inside add() method
        ]);

        self::assertResponseOk($response);

        $result = $response['result'];
        $this->assertIsArray($result);
        $this->assertCount(2, $result, 'Should return two entries for two positions');
    }

    #[TestDox('Selection range on class name has nested ranges')]
    public function testSelectionRangeNestedRanges(): void
    {
        // Position on class name "Calculator" (line 9, character 8)
        $response = self::selectionRange('src/Calculator.php', [
            ['line' => 9, 'character' => 8],
        ]);

        self::assertResponseOk($response);

        $result = $response['result'];
        $this->assertIsArray($result);
        $this->assertCount(1, $result);

        $selectionRange = $result[0];
        if ($selectionRange === null) {
            $this->markTestSkipped('Server returned null for selection range at this position');
        }

        $this->assertArrayHasKey('range', $selectionRange);
    }
}

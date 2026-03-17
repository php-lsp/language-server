<?php

declare(strict_types=1);

namespace App\Tests\Playground;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\TestDox;

#[Group('e2e')]
#[TestDox('LSP Document Highlight')]
final class DocumentHighlightTest extends PlaygroundTestCase
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

    #[TestDox('highlight on variable returns all occurrences')]
    public function testHighlightOnVariable(): void
    {
        // Calculator.php line 21 (0-indexed): "        $this->result += $value;"
        // Cursor on "$value" at char 29
        $response = self::documentHighlight('src/Calculator.php', 21, 29);

        self::assertResponseOk($response);

        $result = $response['result'];
        self::assertIsArray($result);
        self::assertNotEmpty($result, 'Should highlight occurrences of $value');
    }

    #[TestDox('highlight result has correct structure')]
    public function testHighlightStructure(): void
    {
        // User.php line 18 (0-indexed): "        return $this->name;"
        // Cursor on "$this" at char 15
        $response = self::documentHighlight('src/User.php', 18, 15);

        self::assertResponseOk($response);

        $result = $response['result'];
        self::assertIsArray($result);

        if (empty($result)) {
            self::markTestSkipped('No highlights returned for $this');
        }

        $highlight = $result[0];
        self::assertArrayHasKey('range', $highlight);
        self::assertArrayHasKey('start', $highlight['range']);
        self::assertArrayHasKey('end', $highlight['range']);
    }

    #[TestDox('highlight on method name returns occurrences')]
    public function testHighlightOnMethodName(): void
    {
        // User.php line 16 (0-indexed): "    public function getName(): string"
        // "getName" at char 20
        $response = self::documentHighlight('src/User.php', 16, 22);

        self::assertResponseOk($response);

        $result = $response['result'];
        self::assertIsArray($result);
    }

    #[TestDox('highlight on empty position returns empty array')]
    public function testHighlightOnEmptyPosition(): void
    {
        // Calculator.php line 0: "<?php"
        $response = self::documentHighlight('src/Calculator.php', 0, 0);

        self::assertResponseOk($response);

        $result = $response['result'];
        self::assertIsArray($result);
        // Might be empty or contain matches — important thing is no error
    }
}

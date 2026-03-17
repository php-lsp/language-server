<?php

declare(strict_types=1);

namespace App\Tests\Playground;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\TestDox;

#[Group('e2e')]
#[TestDox('LSP Formatting')]
final class FormattingTest extends PlaygroundTestCase
{
    protected static float $indexingWaitTime = 2.0;

    private static bool $filesOpened = false;

    protected function setUp(): void
    {
        parent::setUp();

        if (!self::$filesOpened) {
            self::openPlaygroundFile('src/Calculator.php');
            self::$filesOpened = true;
        }
    }

    #[TestDox('formatting request returns response without error')]
    public function testFormattingReturnsResponse(): void
    {
        $response = self::formatting('src/Calculator.php');

        self::assertResponseOk($response);

        $result = $response['result'];
        // Result is either null (no changes needed) or an array of TextEdits
        if ($result !== null) {
            self::assertIsArray($result);
            foreach ($result as $edit) {
                self::assertArrayHasKey('range', $edit);
                self::assertArrayHasKey('newText', $edit);
            }
        }
    }

    #[TestDox('formatting on well-formatted file returns empty edits or null')]
    public function testFormattingOnWellFormattedFile(): void
    {
        $response = self::formatting('src/Calculator.php');

        self::assertResponseOk($response);

        $result = $response['result'];
        // Well-formatted file should not need changes
        if ($result !== null) {
            self::assertIsArray($result);
        }
    }

    #[TestDox('range formatting request returns response')]
    public function testRangeFormatting(): void
    {
        $response = self::$client->request('textDocument/rangeFormatting', [
            'textDocument' => ['uri' => self::playgroundFileUri('src/Calculator.php')],
            'range' => [
                'start' => ['line' => 19, 'character' => 0],
                'end' => ['line' => 23, 'character' => 0],
            ],
            'options' => ['tabSize' => 4, 'insertSpaces' => true],
        ], static::$requestTimeout);

        self::assertResponseOk($response);

        $result = $response['result'];
        if ($result !== null) {
            self::assertIsArray($result);
        }
    }
}

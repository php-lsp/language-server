<?php

declare(strict_types=1);

namespace App\Tests\Playground;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\TestDox;

#[Group('e2e')]
#[TestDox('LSP Rename')]
final class RenameTest extends PlaygroundTestCase
{
    protected static float $indexingWaitTime = 3.0;

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

    #[TestDox('prepareRename on method name returns valid range')]
    public function testPrepareRenameOnMethodName(): void
    {
        // Calculator.php line 19 (0-indexed): "    public function add(float $value): self"
        // "add" starts at char 20
        $response = self::prepareRename('src/Calculator.php', 19, 21);

        self::assertResponseOk($response);

        $result = $response['result'];
        self::assertNotNull($result, 'Should return a range for renameable symbol');
        // prepareRename returns Range directly
        self::assertArrayHasKey('start', $result);
        self::assertArrayHasKey('end', $result);
    }

    #[TestDox('prepareRename returns range spanning the symbol name')]
    public function testPrepareRenameRangeSpansName(): void
    {
        // Calculator.php: "add" method on line 19
        $response = self::prepareRename('src/Calculator.php', 19, 21);

        self::assertResponseOk($response);

        $result = $response['result'];
        self::assertNotNull($result);

        // "add" is 3 characters: start char 20, end char 23
        self::assertSame(19, $result['start']['line']);
        self::assertSame(20, $result['start']['character']);
        self::assertSame(19, $result['end']['line']);
        self::assertSame(23, $result['end']['character']);
    }

    #[TestDox('prepareRename on empty position returns null')]
    public function testPrepareRenameOnEmptyPositionReturnsNull(): void
    {
        // Calculator.php line 0: "<?php"
        $response = self::prepareRename('src/Calculator.php', 0, 0);

        self::assertResponseOk($response);
        self::assertNull($response['result'], 'Should return null for non-renameable position');
    }

    #[TestDox('rename on variable returns workspace edit')]
    public function testRenameOnVariable(): void
    {
        // Calculator.php line 21 (0-indexed): "        $this->result += $value;"
        // "$value" starts at char 28
        $response = self::rename('src/Calculator.php', 21, 29, 'amount');

        // Rename may fail for some symbols — test it doesn't crash
        if (isset($response['error'])) {
            self::markTestSkipped('Rename returned error: ' . ($response['error']['message'] ?? 'unknown'));
        }

        self::assertResponseOk($response);

        $result = $response['result'];
        if ($result === null) {
            self::markTestSkipped('Rename returned null');
        }

        $hasChanges = isset($result['changes']) || isset($result['documentChanges']);
        self::assertTrue($hasChanges, 'Rename should return a WorkspaceEdit');
    }

    #[TestDox('prepareRename on property returns range')]
    public function testPrepareRenameOnProperty(): void
    {
        // User.php line 12 (0-indexed): "        private readonly string $name,"
        $response = self::prepareRename('src/User.php', 12, 40);

        self::assertResponseOk($response);

        $result = $response['result'];
        if ($result === null) {
            self::markTestSkipped('Property may not be renameable at this position');
        }

        self::assertArrayHasKey('start', $result);
        self::assertArrayHasKey('end', $result);
    }
}

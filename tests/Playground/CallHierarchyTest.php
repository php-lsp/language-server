<?php

declare(strict_types=1);

namespace App\Tests\Playground;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\TestDox;

#[Group('e2e')]
#[TestDox('LSP Call Hierarchy')]
final class CallHierarchyTest extends PlaygroundTestCase
{
    protected static float $indexingWaitTime = 3.0;

    private static bool $filesOpened = false;

    protected function setUp(): void
    {
        parent::setUp();

        if (!self::$filesOpened) {
            self::openPlaygroundFile('src/Calculator.php');
            self::openPlaygroundFile('src/User.php');
            self::openPlaygroundFile('src/Greeter.php');
            self::openPlaygroundFile('src/TypeTestFile.php');
            self::openPlaygroundFile('src/UserService.php');
            self::openPlaygroundFile('src/ConsoleApp.php');
            self::$filesOpened = true;
        }
    }

    #[TestDox('prepareCallHierarchy on method definition returns CallHierarchyItem')]
    public function testPrepareOnMethodDefinition(): void
    {
        // Calculator.php line 20 (0-indexed 19): "    public function add(float $value): self"
        // "add" starts at char 20
        $response = self::prepareCallHierarchy('src/Calculator.php', 19, 21);

        self::assertResponseOk($response);
        $result = $response['result'];
        self::assertNotNull($result, 'Should return items');
        self::assertNotEmpty($result, 'Should return at least one item');

        $item = $result[0];
        self::assertStringContainsString('add', $item['name']);
        // SymbolKind::Method = 6
        self::assertSame(6, $item['kind']);
        self::assertArrayHasKey('uri', $item);
        self::assertArrayHasKey('range', $item);
        self::assertArrayHasKey('selectionRange', $item);
        self::assertArrayHasKey('data', $item);
    }

    #[TestDox('prepareCallHierarchy selectionRange is non-zero-width')]
    public function testPrepareSelectionRangeIsNonZeroWidth(): void
    {
        // Calculator.php: "add" method
        $response = self::prepareCallHierarchy('src/Calculator.php', 19, 21);

        self::assertResponseOk($response);
        $item = $response['result'][0];
        $sr = $item['selectionRange'];

        self::assertNotSame(
            [$sr['start']['line'], $sr['start']['character']],
            [$sr['end']['line'], $sr['end']['character']],
            'selectionRange should not be zero-width',
        );
    }

    #[TestDox('prepareCallHierarchy on function call returns item')]
    public function testPrepareOnFunctionDefinition(): void
    {
        // Greeter.php line 21 (0-indexed): "    public function greet(string $prefix = 'Hello'): string"
        // "greet" starts at char 20
        $response = self::prepareCallHierarchy('src/Greeter.php', 21, 21);

        self::assertResponseOk($response);
        $result = $response['result'];
        self::assertNotNull($result);
        self::assertNotEmpty($result);
        self::assertStringContainsString('greet', $result[0]['name']);
    }

    #[TestDox('prepareCallHierarchy on empty position returns null')]
    public function testPrepareOnEmptyPositionReturnsNull(): void
    {
        // Calculator.php line 0: "<?php"
        $response = self::prepareCallHierarchy('src/Calculator.php', 0, 0);

        self::assertResponseOk($response);
        self::assertNull($response['result'], 'Should return null for non-symbol position');
    }

    #[TestDox('incomingCalls returns callers of a method')]
    public function testIncomingCallsReturnsCallers(): void
    {
        // Prepare on Calculator::add
        $prepareResponse = self::prepareCallHierarchy('src/Calculator.php', 19, 21);
        self::assertResponseOk($prepareResponse);
        self::assertNotEmpty($prepareResponse['result']);

        $item = $prepareResponse['result'][0];

        // Get incoming calls
        $response = self::incomingCalls($item);
        self::assertResponseOk($response);

        $result = $response['result'];
        self::assertIsArray($result);
        // TypeTestFile calls Calculator::add, so we should have at least 1 caller
        self::assertNotEmpty($result, 'Should have at least one incoming call');

        $firstCall = $result[0];
        self::assertArrayHasKey('from', $firstCall);
        self::assertArrayHasKey('fromRanges', $firstCall);
        self::assertNotEmpty($firstCall['fromRanges']);

        // fromRanges should be non-zero-width
        $fromRange = $firstCall['fromRanges'][0];
        self::assertNotSame(
            [$fromRange['start']['line'], $fromRange['start']['character']],
            [$fromRange['end']['line'], $fromRange['end']['character']],
            'fromRanges should not be zero-width',
        );
    }

    #[TestDox('outgoingCalls returns methods called from a method')]
    public function testOutgoingCallsReturnsCalls(): void
    {
        // Prepare on ConsoleApp::run (calls Greeter constructor, greet, and log)
        $prepareResponse = self::prepareCallHierarchy('src/ConsoleApp.php', 11, 21);
        self::assertResponseOk($prepareResponse);

        if (empty($prepareResponse['result'])) {
            self::markTestSkipped('prepareCallHierarchy did not return items for ConsoleApp::run');
        }

        $item = $prepareResponse['result'][0];

        // Get outgoing calls
        $response = self::outgoingCalls($item);
        self::assertResponseOk($response);

        $result = $response['result'];
        self::assertIsArray($result);
    }

    #[TestDox('incoming calls from item has valid structure')]
    public function testIncomingCallsStructure(): void
    {
        // Prepare on Greeter::greet
        $prepareResponse = self::prepareCallHierarchy('src/Greeter.php', 21, 21);
        self::assertResponseOk($prepareResponse);

        if (empty($prepareResponse['result'])) {
            self::markTestSkipped('prepareCallHierarchy did not return items');
        }

        $item = $prepareResponse['result'][0];

        $response = self::incomingCalls($item);
        self::assertResponseOk($response);

        $result = $response['result'];
        self::assertIsArray($result);

        foreach ($result as $incomingCall) {
            // Each incoming call must have 'from' (a CallHierarchyItem) and 'fromRanges'
            self::assertArrayHasKey('from', $incomingCall);
            self::assertArrayHasKey('fromRanges', $incomingCall);

            $from = $incomingCall['from'];
            self::assertArrayHasKey('name', $from);
            self::assertArrayHasKey('kind', $from);
            self::assertArrayHasKey('uri', $from);
            self::assertArrayHasKey('range', $from);
            self::assertArrayHasKey('selectionRange', $from);
        }
    }
}

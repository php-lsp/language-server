<?php

declare(strict_types=1);

namespace App\Tests\Playground;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\TestDox;

#[Group('e2e')]
#[TestDox('LSP Document Symbol')]
final class DocumentSymbolTest extends PlaygroundTestCase
{
    protected static float $indexingWaitTime = 2.0;

    private static bool $filesOpened = false;

    protected function setUp(): void
    {
        parent::setUp();

        if (!self::$filesOpened) {
            self::openPlaygroundFile('src/Calculator.php');
            self::openPlaygroundFile('src/StatusEnum.php');
            self::openPlaygroundFile('src/AppInterface.php');
            self::$filesOpened = true;
        }
    }

    #[TestDox('documentSymbol returns symbols for a class file')]
    public function testDocumentSymbolReturnsClassSymbols(): void
    {
        $response = self::documentSymbol('src/Calculator.php');

        self::assertResponseOk($response);

        $result = $response['result'];
        self::assertIsArray($result);
        self::assertNotEmpty($result, 'Should return symbols for Calculator.php');

        // Find the Calculator class symbol
        $names = array_map(static fn(array $s): string => $s['name'], $result);
        self::assertContains('Calculator', $names, 'Should contain Calculator class symbol');
    }

    #[TestDox('class symbol contains method children')]
    public function testClassSymbolContainsMethods(): void
    {
        $response = self::documentSymbol('src/Calculator.php');
        self::assertResponseOk($response);

        $classSymbol = null;
        foreach ($response['result'] as $symbol) {
            if ($symbol['name'] === 'Calculator') {
                $classSymbol = $symbol;
                break;
            }
        }

        self::assertNotNull($classSymbol, 'Should find Calculator symbol');
        self::assertArrayHasKey('children', $classSymbol);
        self::assertNotEmpty($classSymbol['children'], 'Calculator should have child symbols');

        $childNames = array_map(static fn(array $s): string => $s['name'], $classSymbol['children']);
        self::assertContains('add', $childNames, 'Should contain add method');
        self::assertContains('subtract', $childNames, 'Should contain subtract method');
        self::assertContains('multiply', $childNames, 'Should contain multiply method');
        self::assertContains('divide', $childNames, 'Should contain divide method');
        self::assertContains('getResult', $childNames, 'Should contain getResult method');
        self::assertContains('reset', $childNames, 'Should contain reset method');
    }

    #[TestDox('symbol has correct structure')]
    public function testSymbolStructure(): void
    {
        $response = self::documentSymbol('src/Calculator.php');
        self::assertResponseOk($response);

        $classSymbol = $response['result'][0];

        // DocumentSymbol structure
        self::assertArrayHasKey('name', $classSymbol);
        self::assertArrayHasKey('kind', $classSymbol);
        self::assertArrayHasKey('range', $classSymbol);
        self::assertArrayHasKey('selectionRange', $classSymbol);

        // SymbolKind::Class_ = 5
        self::assertSame(5, $classSymbol['kind'], 'Calculator should be SymbolKind::Class (5)');

        // Range structure
        self::assertArrayHasKey('start', $classSymbol['range']);
        self::assertArrayHasKey('end', $classSymbol['range']);
        self::assertArrayHasKey('line', $classSymbol['range']['start']);
        self::assertArrayHasKey('character', $classSymbol['range']['start']);
    }

    #[TestDox('documentSymbol returns enum symbol')]
    public function testDocumentSymbolReturnsEnumSymbol(): void
    {
        $response = self::documentSymbol('src/StatusEnum.php');

        self::assertResponseOk($response);
        $result = $response['result'];
        self::assertNotEmpty($result);

        $names = array_map(static fn(array $s): string => $s['name'], $result);
        self::assertContains('StatusEnum', $names, 'Should contain StatusEnum symbol');
    }

    #[TestDox('documentSymbol returns interface symbol')]
    public function testDocumentSymbolReturnsInterfaceSymbol(): void
    {
        $response = self::documentSymbol('src/AppInterface.php');

        self::assertResponseOk($response);
        $result = $response['result'];
        self::assertNotEmpty($result);

        $names = array_map(static fn(array $s): string => $s['name'], $result);
        self::assertContains('AppInterface', $names, 'Should contain AppInterface symbol');
    }
}

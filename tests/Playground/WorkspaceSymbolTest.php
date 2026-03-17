<?php

declare(strict_types=1);

namespace App\Tests\Playground;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\TestDox;

#[Group('e2e')]
#[TestDox('LSP Workspace Symbol')]
final class WorkspaceSymbolTest extends PlaygroundTestCase
{
    protected static float $indexingWaitTime = 3.0;

    #[TestDox('workspace/symbol returns symbols for query')]
    public function testWorkspaceSymbolReturnsResults(): void
    {
        $response = self::workspaceSymbol('Calculator');

        self::assertResponseOk($response);

        $result = $response['result'];
        self::assertIsArray($result);
        self::assertNotEmpty($result, 'Should return symbols matching "Calculator"');

        $names = array_map(static fn(array $s): string => $s['name'], $result);
        self::assertContains('Playground\Calculator', $names, 'Should find Calculator class');
    }

    #[TestDox('workspace/symbol returns interface symbols')]
    public function testWorkspaceSymbolFindsInterface(): void
    {
        $response = self::workspaceSymbol('AppInterface');

        self::assertResponseOk($response);

        $result = $response['result'];
        self::assertNotEmpty($result);

        $names = array_map(static fn(array $s): string => $s['name'], $result);
        self::assertContains('Playground\AppInterface', $names);
    }

    #[TestDox('workspace/symbol returns enum symbols')]
    public function testWorkspaceSymbolFindsEnum(): void
    {
        $response = self::workspaceSymbol('StatusEnum');

        self::assertResponseOk($response);

        $result = $response['result'];
        self::assertNotEmpty($result);

        $names = array_map(static fn(array $s): string => $s['name'], $result);
        self::assertContains('Playground\StatusEnum', $names);
    }

    #[TestDox('workspace/symbol returns method symbols')]
    public function testWorkspaceSymbolFindsMethods(): void
    {
        $response = self::workspaceSymbol('getResult');

        self::assertResponseOk($response);

        $result = $response['result'];
        self::assertNotEmpty($result, 'Should return symbols matching "getResult"');
    }

    #[TestDox('workspace/symbol result has correct structure')]
    public function testWorkspaceSymbolStructure(): void
    {
        $response = self::workspaceSymbol('User');

        self::assertResponseOk($response);

        $result = $response['result'];
        self::assertNotEmpty($result);

        $symbol = $result[0];
        self::assertArrayHasKey('name', $symbol);
        self::assertArrayHasKey('kind', $symbol);
        self::assertArrayHasKey('location', $symbol);
        self::assertArrayHasKey('uri', $symbol['location']);
        self::assertArrayHasKey('range', $symbol['location']);
    }

    #[TestDox('workspace/symbol with empty query returns all symbols')]
    public function testWorkspaceSymbolEmptyQueryReturnsAll(): void
    {
        $response = self::workspaceSymbol('');

        self::assertResponseOk($response);

        $result = $response['result'];
        self::assertIsArray($result);
        self::assertGreaterThan(5, count($result), 'Empty query should return many symbols');
    }
}

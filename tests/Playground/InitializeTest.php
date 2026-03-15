<?php

declare(strict_types=1);

namespace App\Tests\Playground;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\TestDox;

#[Group('e2e')]
#[TestDox('LSP Initialize')]
final class InitializeTest extends PlaygroundTestCase
{
    #[TestDox('Server returns a valid InitializeResult with all capabilities')]
    public function testInitializeResult(): void
    {
        self::assertResponseOk(self::$initializeResult);

        $capabilities = self::$initializeResult['result']['capabilities'];

        self::assertSame(2, $capabilities['textDocumentSync']);
        self::assertSame(['.', ':', '<', '\'', '"', '`'], $capabilities['completionProvider']['triggerCharacters']);
        self::assertIsArray($capabilities['hoverProvider']);
        self::assertSame(['(', ',', ':', ' '], $capabilities['signatureHelpProvider']['triggerCharacters']);
        self::assertIsArray($capabilities['declarationProvider']);
        self::assertIsArray($capabilities['definitionProvider']);
        self::assertIsArray($capabilities['typeDefinitionProvider']);
        self::assertIsArray($capabilities['implementationProvider']);
        self::assertIsArray($capabilities['referencesProvider']);
        self::assertIsArray($capabilities['documentHighlightProvider']);
        self::assertIsArray($capabilities['documentSymbolProvider']);
        self::assertIsArray($capabilities['workspaceSymbolProvider']);
        self::assertSame(['quickfix', 'source.organizeImports'], $capabilities['codeActionProvider']['codeActionKinds']);
        self::assertIsArray($capabilities['documentFormattingProvider']);
        self::assertIsArray($capabilities['documentRangeFormattingProvider']);
        self::assertTrue($capabilities['renameProvider']['prepareProvider']);
        self::assertTrue($capabilities['diagnosticProvider']['interFileDependencies']);
        self::assertFalse($capabilities['diagnosticProvider']['workspaceDiagnostics']);
        self::assertTrue($capabilities['workspace']['workspaceFolders']['supported']);
        self::assertTrue($capabilities['workspace']['workspaceFolders']['changeNotifications']);
        self::assertSame('PHP Server', self::$initializeResult['result']['serverInfo']['name']);
        self::assertSame('0.0.1', self::$initializeResult['result']['serverInfo']['version']);
    }
}

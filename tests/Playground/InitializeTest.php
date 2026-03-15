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
        self::assertJsonEquals([
            'result' => [
                'capabilities' => [
                    'textDocumentSync' => 2,
                    'completionProvider' => [
                        'triggerCharacters' => ['.', ':', '<', '\'', '"', '`'],
                    ],
                    'hoverProvider' => true,
                    'signatureHelpProvider' => [
                        'triggerCharacters' => ['(', ',', ':', ' '],
                    ],
                    'declarationProvider' => true,
                    'definitionProvider' => true,
                    'typeDefinitionProvider' => true,
                    'implementationProvider' => true,
                    'referencesProvider' => true,
                    'documentHighlightProvider' => true,
                    'documentSymbolProvider' => true,
                    'workspaceSymbolProvider' => true,
                    'codeActionProvider' => [
                        'codeActionKinds' => [
                            'quickfix',
                            'source.organizeImports',
                        ],
                    ],
                    'documentFormattingProvider' => true,
                    'documentRangeFormattingProvider' => true,
                    'renameProvider' => [
                        'prepareProvider' => true,
                    ],
                    'diagnosticProvider' => [
                        'interFileDependencies' => true,
                        'workspaceDiagnostics' => false,
                    ],
                    'workspace' => [
                        'workspaceFolders' => [
                            'supported' => true,
                            'changeNotifications' => true,
                        ],
                    ],
                ],
                'serverInfo' => [
                    'name' => 'PHP Server',
                    'version' => '0.0.1',
                ],
            ],
        ], self::$initializeResult);
    }
}

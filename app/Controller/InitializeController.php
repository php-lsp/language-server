<?php

declare(strict_types=1);

namespace App\Controller;

use App\Module\Indexing\Indexer;
use App\Module\Workspace\ProjectManager;
use Lsp\Kernel\Attribute\AsController;
use Lsp\Protocol\Type\CodeActionOptions;
use Lsp\Protocol\Type\CompletionOptions;
use Lsp\Protocol\Type\DeclarationOptions;
use Lsp\Protocol\Type\DefinitionOptions;
use Lsp\Protocol\Type\DiagnosticOptions;
use Lsp\Protocol\Type\DocumentFormattingOptions;
use Lsp\Protocol\Type\DocumentHighlightOptions;
use Lsp\Protocol\Type\DocumentRangeFormattingOptions;
use Lsp\Protocol\Type\DocumentSymbolOptions;
use Lsp\Protocol\Type\FoldingRangeOptions;
use Lsp\Protocol\Type\HoverOptions;
use Lsp\Protocol\Type\ImplementationOptions;
use Lsp\Protocol\Type\InitializeParams;
use Lsp\Protocol\Type\InitializeResult;
use Lsp\Protocol\Type\ReferenceOptions;
use Lsp\Protocol\Type\RenameOptions;
use Lsp\Protocol\Type\SelectionRangeOptions;
use Lsp\Protocol\Type\ServerCapabilities;
use Lsp\Protocol\Type\ServerInfo;
use Lsp\Protocol\Type\SignatureHelpOptions;
use Lsp\Protocol\Type\TextDocumentSyncKind;
use Lsp\Protocol\Type\TypeDefinitionOptions;
use Lsp\Protocol\Type\WorkspaceFolder;
use Lsp\Protocol\Type\WorkspaceFoldersServerCapabilities;
use Lsp\Protocol\Type\WorkspaceOptions;
use Lsp\Protocol\Type\WorkspaceSymbolOptions;
use Lsp\Router\Attribute\Route;
use Lsp\Workspace\Project\ProjectFactoryInterface;
use Psr\Log\LoggerInterface;

#[AsController, Route('initialize')]
final class InitializeController
{
    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly Indexer $indexer,
        private readonly ProjectFactoryInterface $projectFactory,
        private readonly ProjectManager $projectManager,
    ) {}

    public function __invoke(InitializeParams $request): InitializeResult
    {
        $this->logger->info('LSP Started');
        // Example workspace folders
        foreach ($request->workspaceFolders ?? [] as $folder) {
            $this->walkWorkspaceFolder($folder);
        }

        return new InitializeResult(
            capabilities: new ServerCapabilities(
                textDocumentSync: TextDocumentSyncKind::Incremental,
                completionProvider: new CompletionOptions(
                    triggerCharacters: ['.', ':', '<', '\'', '"', '`'],
                ),
                hoverProvider: new HoverOptions(),
                signatureHelpProvider: new SignatureHelpOptions(
                    triggerCharacters: ['(', ',', ':', ' '],
                ),
                declarationProvider: new DeclarationOptions(),
                definitionProvider: new DefinitionOptions(),
                typeDefinitionProvider: new TypeDefinitionOptions(),
                implementationProvider: new ImplementationOptions(),
                documentHighlightProvider: new DocumentHighlightOptions(),
                foldingRangeProvider: new FoldingRangeOptions(),
                documentFormattingProvider: new DocumentFormattingOptions(),
                documentRangeFormattingProvider: new DocumentRangeFormattingOptions(),
                codeActionProvider: new CodeActionOptions(
                    codeActionKinds: [
                        'quickfix',
                        'source.organizeImports',
                    ],
                ),
                referencesProvider: new ReferenceOptions(),
                renameProvider: new RenameOptions(
                    prepareProvider: true,
                ),
                documentSymbolProvider: new DocumentSymbolOptions(),
                diagnosticProvider: new DiagnosticOptions(
                    interFileDependencies: true,
                    workspaceDiagnostics: false,
                ),
                selectionRangeProvider: new SelectionRangeOptions(),
                workspaceSymbolProvider: new WorkspaceSymbolOptions(),
                workspace: new WorkspaceOptions(
                    workspaceFolders: new WorkspaceFoldersServerCapabilities(
                        supported: true,
                        changeNotifications: true,
                    ),
                ),
            ),
            serverInfo: new ServerInfo(
                name: 'PHP Server',
                version: '0.0.1',
            ),
        );
    }

    private function walkWorkspaceFolder(WorkspaceFolder $folder): void
    {
        $project = $this->projectFactory->create($folder->uri, $folder->name);
        $this->projectManager->setProject($project);

        $this->indexer->index($project);
    }
}

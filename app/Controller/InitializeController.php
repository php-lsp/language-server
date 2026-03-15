<?php

declare(strict_types=1);

namespace App\Controller;

use App\Module\Indexing\Indexer;
use App\Module\Notification\ServerNotificationSender;
use App\Module\Workspace\ProjectManager;
use Lsp\Kernel\Attribute\AsController;
use Lsp\Protocol\Type\CodeActionOptions;
use Lsp\Protocol\Type\CompletionOptions;
use Lsp\Protocol\Type\DiagnosticOptions;
use Lsp\Protocol\Type\InitializeParams;
use Lsp\Protocol\Type\InitializeResult;
use Lsp\Protocol\Type\RenameOptions;
use Lsp\Protocol\Type\ServerCapabilities;
use Lsp\Protocol\Type\ServerInfo;
use Lsp\Protocol\Type\SignatureHelpOptions;
use Lsp\Protocol\Type\TextDocumentSyncKind;
use Lsp\Protocol\Type\WorkspaceFolder;
use Lsp\Protocol\Type\WorkspaceFoldersServerCapabilities;
use Lsp\Protocol\Type\WorkspaceOptions;
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
        private ServerNotificationSender $notificationSender,
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
                hoverProvider: true,
                signatureHelpProvider: new SignatureHelpOptions(
                    triggerCharacters: ['(', ',', ':', ' '],
                ),
                declarationProvider: true,
                definitionProvider: true,
                typeDefinitionProvider: true,
                implementationProvider: true,
                documentHighlightProvider: true,
                documentFormattingProvider: true,
                documentRangeFormattingProvider: true,
                codeActionProvider: new CodeActionOptions(
                    codeActionKinds: [
                        'quickfix',
                        'source.organizeImports',
                    ],
                ),
                referencesProvider: true,
                renameProvider: new RenameOptions(
                    prepareProvider: true,
                ),
                documentSymbolProvider: true,
                diagnosticProvider: new DiagnosticOptions(
                    interFileDependencies: true,
                    workspaceDiagnostics: false,
                ),
                workspaceSymbolProvider: true,
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

        $start = microtime(true);
        $this->logger->info('Indexing project: ' . $folder->uri);

        $this->indexer->index($project);

        $message = 'Indexing finished in ' . round(microtime(true) - $start, 2) . ' seconds';

        $this->logger->info($message);
        $this->notificationSender->showMessage($message);
    }
}

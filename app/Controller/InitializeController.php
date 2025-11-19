<?php

declare(strict_types=1);

namespace App\Controller;

use App\Module\Indexing\Indexer;
use App\Module\Workspace\ProjectManager;
use Lsp\Kernel\Attribute\AsController;
use Lsp\Protocol\Type\CodeLensOptions;
use Lsp\Protocol\Type\CompletionOptions;
use Lsp\Protocol\Type\DiagnosticOptions;
use Lsp\Protocol\Type\DocumentSymbolOptions;
use Lsp\Protocol\Type\FileOperationOptions;
use Lsp\Protocol\Type\FileOperationRegistrationOptions;
use Lsp\Protocol\Type\HoverOptions;
use Lsp\Protocol\Type\InitializeParams;
use Lsp\Protocol\Type\InitializeResult;
use Lsp\Protocol\Type\ReferenceOptions;
use Lsp\Protocol\Type\ServerCapabilities;
use Lsp\Protocol\Type\ServerInfo;
use Lsp\Protocol\Type\TextDocumentSyncKind;
use Lsp\Protocol\Type\WorkspaceFolder;
use Lsp\Protocol\Type\WorkspaceFoldersServerCapabilities;
use Lsp\Protocol\Type\WorkspaceOptions;
use Lsp\Router\Attribute\Route;
use Lsp\Workspace\File\VirtualFileInterface;
use Lsp\Workspace\Project\ProjectFactory;
use Lsp\Workspace\Project\ProjectInterface;
use Psr\Log\LoggerInterface;
use function str_repeat;

#[AsController, Route('initialize')]
final class InitializeController
{
    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly Indexer $indexer,
        private readonly ProjectManager $projectManager,
    )
    {
    }

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
                    triggerCharacters: [],
//                    triggerCharacters: ['.', ':', '<', '\'', '"', '`'],
                ),
                hoverProvider: true,
//                codeLensProvider: new CodeLensOptions(
//                    resolveProvider: true,
//                ),
                referencesProvider: new ReferenceOptions(
                    workDoneProgress: null,
                ),
                documentSymbolProvider: new DocumentSymbolOptions(),
                diagnosticProvider: new DiagnosticOptions(
                    interFileDependencies: true,
                    workspaceDiagnostics: false,
                    identifier: null,
                    workDoneProgress: null
                ),
                workspace: new WorkspaceOptions(
                    workspaceFolders: new WorkspaceFoldersServerCapabilities(
                        supported: true,
                        changeNotifications: true,
                    ),
                    fileOperations: new FileOperationOptions(
                        didCreate: new FileOperationRegistrationOptions(
                            filters: [],
                        ),
                        willCreate: new FileOperationRegistrationOptions(
                            filters: [],
                        ),
                        didRename: new FileOperationRegistrationOptions(
                            filters: [],
                        ),
                        willRename: new FileOperationRegistrationOptions(
                            filters: [],
                        ),
                        didDelete: new FileOperationRegistrationOptions(
                            filters: [],
                        ),
                        willDelete: new FileOperationRegistrationOptions(
                            filters: [],
                        ),
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
        $projects = new ProjectFactory();
        $project = $projects->create($folder->uri, $folder->name);
        $this->projectManager->setProject($project);

        $this->indexer->index($project);
    }
}

<?php

declare(strict_types=1);

namespace App\Tests\Unit\Controller;

use App\Controller\InitializeController;
use App\Module\Indexing\Indexer;
use App\Module\Indexing\IndexerFileCollector;
use App\Module\Indexing\IndexingStatus;
use App\Module\Notification\ActiveConnectionProvider;
use App\Module\Notification\ProgressNotifier;
use App\Module\Notification\ServerNotificationSender;
use App\Module\Workspace\ProjectManager;
use App\Tests\TestCase;
use Lsp\Dispatcher\Result\Provider\ResultProviderInterface;
use Lsp\Protocol\Type\InitializeParams;
use Lsp\Protocol\Type\InitializeResult;
use Lsp\Protocol\Type\WorkspaceFolder;
use Lsp\Workspace\Project\Project;
use Lsp\Workspace\Project\ProjectFactoryInterface;
use Psr\Log\LoggerInterface;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\TestDox;

#[Group('unit')]
final class InitializeControllerTest extends TestCase
{
    #[TestDox('returns InitializeResult with server capabilities')]
    public function testReturnsInitializeResult(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $indexer = (new \ReflectionClass(Indexer::class))->newInstanceWithoutConstructor();
        $projectFactory = $this->createMock(ProjectFactoryInterface::class);
        $projectManager = new ProjectManager();

        $controller = new InitializeController($logger, $indexer, $projectFactory, $projectManager);

        $params = new InitializeParams(capabilities: new \Lsp\Protocol\Type\ClientCapabilities());

        $result = $controller($params);

        $this->assertInstanceOf(InitializeResult::class, $result);
        $this->assertSame('PHP Server', $result->serverInfo->name);
        $this->assertNotNull($result->capabilities->completionProvider);
        $this->assertNotNull($result->capabilities->hoverProvider);
        $this->assertNotNull($result->capabilities->signatureHelpProvider);
        $this->assertNotNull($result->capabilities->diagnosticProvider);
    }

    #[TestDox('processes workspace folders')]
    public function testProcessesWorkspaceFolders(): void
    {
        $logger = $this->createMock(LoggerInterface::class);

        $connectionProvider = new ActiveConnectionProvider();
        $resultProvider = $this->createMock(ResultProviderInterface::class);
        $resultProvider->method('getResult')->willReturn([]);
        $sender = new ServerNotificationSender($connectionProvider, $resultProvider, $logger);
        $progressNotifier = new ProgressNotifier($sender);

        $fileCollector = $this->createMock(IndexerFileCollector::class);
        $fileCollector->method('collect')->willReturn([]);

        $indexer = new Indexer(
            [],
            new \App\Module\Indexing\Storage\InMemoryStorage(),
            $logger,
            $fileCollector,
            $progressNotifier,
            new IndexingStatus(),
        );

        $projectFactory = $this->createMock(ProjectFactoryInterface::class);
        $project = $this->createMock(Project::class);
        $project->method('getIterator')->willReturn(new \ArrayIterator([]));
        $uriProp = new \ReflectionProperty(Project::class, 'uri');
        $uriProp->setValue($project, \Lsp\Workspace\Uri\Uri::createLocal('file:///workspace'));
        $projectFactory->method('create')->willReturn($project);
        $projectManager = new ProjectManager();

        $controller = new InitializeController($logger, $indexer, $projectFactory, $projectManager);

        $folder = new WorkspaceFolder(uri: 'file:///workspace', name: 'test');
        $params = new InitializeParams(
            capabilities: new \Lsp\Protocol\Type\ClientCapabilities(),
            workspaceFolders: [$folder],
        );

        $result = $controller($params);

        $this->assertInstanceOf(InitializeResult::class, $result);
        $this->assertSame($project, $projectManager->getProject());
    }
}

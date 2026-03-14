<?php

declare(strict_types=1);

namespace App\Tests\Unit\Controller;

use App\Controller\InitializeController;
use App\Module\Indexing\Indexer;
use App\Module\Notification\ServerNotificationSender;
use App\Module\Workspace\ProjectManager;
use App\Tests\TestCase;
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
        $sender = (new \ReflectionClass(ServerNotificationSender::class))->newInstanceWithoutConstructor();

        $controller = new InitializeController($logger, $indexer, $projectFactory, $projectManager, $sender);

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
        $indexer = (new \ReflectionClass(Indexer::class))->newInstanceWithoutConstructor();
        $projectFactory = $this->createMock(ProjectFactoryInterface::class);
        $project = $this->createMock(Project::class);
        $projectFactory->method('create')->willReturn($project);
        $projectManager = new ProjectManager();
        $sender = (new \ReflectionClass(ServerNotificationSender::class))->newInstanceWithoutConstructor();

        $controller = new InitializeController($logger, $indexer, $projectFactory, $projectManager, $sender);

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

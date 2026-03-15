<?php

declare(strict_types=1);

namespace App\Tests\Unit\Controller\TextDocument;

use App\Controller\TextDocument\PublishDiagnosticsController;
use App\Module\Notification\ActiveConnectionProvider;
use App\Module\Notification\ServerNotificationSender;
use App\Tests\TestCase;
use Lsp\Contracts\Server\ConnectionInterface;
use Lsp\Dispatcher\Result\Provider\ResultProviderInterface;
use Lsp\Protocol\Type\PublishDiagnosticsParams;
use Psr\Log\LoggerInterface;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\TestDox;

#[Group('unit')]
final class PublishDiagnosticsControllerTest extends TestCase
{
    #[TestDox('forwards diagnostics notification')]
    public function testForwardsDiagnostics(): void
    {
        $connection = $this->createMock(ConnectionInterface::class);
        $connection->expects($this->once())->method('notify');

        $connectionProvider = new ActiveConnectionProvider();
        $connectionProvider->set($connection);

        $resultProvider = $this->createMock(ResultProviderInterface::class);
        $resultProvider->method('getResult')->willReturn(['uri' => 'file:///test.php', 'diagnostics' => []]);

        $logger = $this->createMock(LoggerInterface::class);

        $sender = new ServerNotificationSender($connectionProvider, $resultProvider, $logger);
        $controller = new PublishDiagnosticsController($sender);

        $params = new PublishDiagnosticsParams(
            uri: 'file:///test.php',
            diagnostics: [],
        );

        $controller($params);
    }
}

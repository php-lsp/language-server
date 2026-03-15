<?php

declare(strict_types=1);

namespace App\Tests\Unit\Controller;

use App\Controller\InitializedController;
use App\Module\Notification\ActiveConnectionProvider;
use App\Module\Notification\ServerNotificationSender;
use App\Tests\TestCase;
use Lsp\Dispatcher\Result\Provider\ResultProviderInterface;
use Lsp\Protocol\Type\InitializedParams;
use Psr\Log\LoggerInterface;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\TestDox;

#[Group('unit')]
final class InitializedControllerTest extends TestCase
{
    #[TestDox('logs initialization')]
    public function testLogsInit(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->atLeastOnce())->method('info');

        $connectionProvider = new ActiveConnectionProvider();
        $resultProvider = $this->createMock(ResultProviderInterface::class);
        $resultProvider->method('getResult')->willReturn([]);

        $notifier = new ServerNotificationSender($connectionProvider, $resultProvider, $logger);

        $controller = new InitializedController($logger, $notifier);
        // showMessage will fail (no connection) but controller still logs
        $controller(new InitializedParams());
    }
}

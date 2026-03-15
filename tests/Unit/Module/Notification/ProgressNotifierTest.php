<?php

declare(strict_types=1);

namespace App\Tests\Unit\Module\Notification;

use App\Module\Notification\ActiveConnectionProvider;
use App\Module\Notification\ProgressNotifier;
use App\Module\Notification\ServerNotificationSender;
use App\Tests\TestCase;
use Lsp\Contracts\Server\ConnectionInterface;
use Lsp\Dispatcher\Result\Provider\ResultProviderInterface;
use Psr\Log\LoggerInterface;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\TestDox;

#[Group('unit')]
final class ProgressNotifierTest extends TestCase
{
    private function createNotifier(?ConnectionInterface $connection = null): ProgressNotifier
    {
        $connectionProvider = new ActiveConnectionProvider();
        if ($connection !== null) {
            $connectionProvider->set($connection);
        }

        $resultProvider = $this->createMock(ResultProviderInterface::class);
        $resultProvider->method('getResult')->willReturn([]);

        $logger = $this->createMock(LoggerInterface::class);

        $sender = new ServerNotificationSender($connectionProvider, $resultProvider, $logger);

        return new ProgressNotifier($sender);
    }

    #[TestDox('create sends window/workDoneProgress/create notification')]
    public function testCreateSendsNotification(): void
    {
        $connection = $this->createMock(ConnectionInterface::class);
        $connection->expects($this->once())->method('notify');

        $notifier = $this->createNotifier($connection);
        $notifier->create('test-progress');
    }

    #[TestDox('begin sends $/progress notification with begin kind')]
    public function testBeginSendsNotification(): void
    {
        $connection = $this->createMock(ConnectionInterface::class);
        $connection->expects($this->once())->method('notify');

        $notifier = $this->createNotifier($connection);
        $notifier->begin('test-progress', 'Indexing', 'Starting...', 0);
    }

    #[TestDox('report sends $/progress notification with report kind')]
    public function testReportSendsNotification(): void
    {
        $connection = $this->createMock(ConnectionInterface::class);
        $connection->expects($this->once())->method('notify');

        $notifier = $this->createNotifier($connection);
        $notifier->report('test-progress', '50/100 files', 50);
    }

    #[TestDox('end sends $/progress notification with end kind')]
    public function testEndSendsNotification(): void
    {
        $connection = $this->createMock(ConnectionInterface::class);
        $connection->expects($this->once())->method('notify');

        $notifier = $this->createNotifier($connection);
        $notifier->end('test-progress', 'Done');
    }

    #[TestDox('silently skips when no connection available')]
    public function testSkipsWithoutConnection(): void
    {
        $notifier = $this->createNotifier();
        $notifier->create('test-progress');

        $this->expectNotToPerformAssertions();
    }

    #[TestDox('clamps percentage to valid range')]
    public function testClampsPercentage(): void
    {
        $connection = $this->createMock(ConnectionInterface::class);
        $connection->expects($this->exactly(2))->method('notify');

        $notifier = $this->createNotifier($connection);

        $notifier->report('test', 'msg', -10);
        $notifier->report('test', 'msg', 200);
    }
}

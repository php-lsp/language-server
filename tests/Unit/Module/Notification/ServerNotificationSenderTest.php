<?php

declare(strict_types=1);

namespace App\Tests\Unit\Module\Notification;

use App\Module\Notification\ActiveConnectionProvider;
use App\Module\Notification\Result;
use App\Module\Notification\ServerNotificationSender;
use App\Tests\TestCase;
use Lsp\Contracts\Server\ConnectionInterface;
use Lsp\Dispatcher\Result\Provider\ResultProviderInterface;
use Psr\Log\LoggerInterface;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\TestDox;

#[Group('unit')]
final class ServerNotificationSenderTest extends TestCase
{
    private function createSender(
        ?ConnectionInterface $connection = null,
    ): ServerNotificationSender {
        $connectionProvider = new ActiveConnectionProvider();
        if ($connection !== null) {
            $connectionProvider->set($connection);
        }

        $resultProvider = $this->createMock(ResultProviderInterface::class);
        $resultProvider->method('getResult')->willReturn(['method' => 'test']);

        $logger = $this->createMock(LoggerInterface::class);

        return new ServerNotificationSender($connectionProvider, $resultProvider, $logger);
    }

    #[TestDox('returns error when no active connection')]
    public function testReturnsErrorWhenNoConnection(): void
    {
        $sender = $this->createSender();

        $result = $sender->sendRawNotification('test/method', ['data' => 1]);

        $this->assertFalse($result->success);
        $this->assertSame('No active connection available', $result->error);
    }

    #[TestDox('sends notification successfully')]
    public function testSendsNotificationSuccessfully(): void
    {
        $connection = $this->createMock(ConnectionInterface::class);
        $connection->expects($this->once())->method('notify');

        $sender = $this->createSender($connection);

        $result = $sender->sendRawNotification('test/method', ['data' => 1]);

        $this->assertTrue($result->success);
    }

    #[TestDox('returns error when notification fails')]
    public function testReturnsErrorOnFailure(): void
    {
        $connection = $this->createMock(ConnectionInterface::class);
        $connection->method('notify')->willThrowException(new \RuntimeException('Connection lost'));

        $sender = $this->createSender($connection);

        $result = $sender->sendRawNotification('test/method', null);

        $this->assertFalse($result->success);
        $this->assertSame('Connection lost', $result->error);
    }

    #[TestDox('showMessage delegates to sendRawNotification')]
    public function testShowMessageDelegatesToSend(): void
    {
        $connection = $this->createMock(ConnectionInterface::class);
        $connection->expects($this->once())->method('notify');

        $sender = $this->createSender($connection);

        $result = $sender->showMessage('Hello');

        $this->assertInstanceOf(Result::class, $result);
        $this->assertTrue($result->success);
    }
}

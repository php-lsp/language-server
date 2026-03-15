<?php

declare(strict_types=1);

namespace App\Tests\Unit\Listener;

use App\Listener\ExceptionNotificationListener;
use App\Module\Notification\ActiveConnectionProvider;
use App\Module\Notification\ServerNotificationSender;
use App\Tests\TestCase;
use Lsp\Contracts\Rpc\Message\FailureResponseInterface;
use Lsp\Contracts\Server\ConnectionInterface;
use Lsp\Dispatcher\Result\Provider\ResultProviderInterface;
use Lsp\Server\Event\Message\FailureResponseSent;
use Psr\Log\LoggerInterface;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\TestDox;

#[Group('unit')]
final class ExceptionNotificationListenerTest extends TestCase
{
    private function createListener(?ConnectionInterface $connection = null): ExceptionNotificationListener
    {
        $connectionProvider = new ActiveConnectionProvider();
        if ($connection !== null) {
            $connectionProvider->set($connection);
        }

        $resultProvider = $this->createMock(ResultProviderInterface::class);
        $resultProvider->method('getResult')->willReturn([]);

        $logger = $this->createMock(LoggerInterface::class);

        $sender = new ServerNotificationSender($connectionProvider, $resultProvider, $logger);

        return new ExceptionNotificationListener($sender);
    }

    #[TestDox('sends error notification for server exceptions')]
    public function testSendsErrorNotification(): void
    {
        $connection = $this->createMock(ConnectionInterface::class);
        $connection->expects($this->once())->method('notify');

        $listener = $this->createListener($connection);

        $response = $this->createMock(FailureResponseInterface::class);
        $response->method('getCode')->willReturn(-32_000);
        $response->method('getMessage')->willReturn('Internal error');

        $event = new FailureResponseSent($response, $connection);
        $listener($event);
    }

    #[TestDox('ignores MethodNotFound errors')]
    public function testIgnoresMethodNotFound(): void
    {
        $connection = $this->createMock(ConnectionInterface::class);
        $connection->expects($this->never())->method('notify');

        $listener = $this->createListener($connection);

        $response = $this->createMock(FailureResponseInterface::class);
        $response->method('getCode')->willReturn(-32_601);
        $response->method('getMessage')->willReturn('Method not found');

        $event = new FailureResponseSent($response, $connection);
        $listener($event);
    }

    #[TestDox('ignores ParseError errors')]
    public function testIgnoresParseError(): void
    {
        $connection = $this->createMock(ConnectionInterface::class);
        $connection->expects($this->never())->method('notify');

        $listener = $this->createListener($connection);

        $response = $this->createMock(FailureResponseInterface::class);
        $response->method('getCode')->willReturn(-32_700);
        $response->method('getMessage')->willReturn('Parse error');

        $event = new FailureResponseSent($response, $connection);
        $listener($event);
    }
}

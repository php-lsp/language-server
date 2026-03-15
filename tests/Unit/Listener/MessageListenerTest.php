<?php

declare(strict_types=1);

namespace App\Tests\Unit\Listener;

use App\Listener\MessageListener;
use App\Tests\TestCase;
use Lsp\Contracts\Rpc\Message\MessageInterface;
use Lsp\Contracts\Server\ConnectionInterface;
use Lsp\Server\Event\Message\MessageReceived;
use Psr\Log\LoggerInterface;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\TestDox;

#[Group('unit')]
final class MessageListenerTest extends TestCase
{
    #[TestDox('logs debug message on event')]
    public function testLogsMessage(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())->method('debug');

        $listener = new MessageListener($logger);

        $message = $this->createMock(MessageInterface::class);
        $connection = $this->createMock(ConnectionInterface::class);
        $event = new MessageReceived($message, $connection);

        $listener($event);
    }
}

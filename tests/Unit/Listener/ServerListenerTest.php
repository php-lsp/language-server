<?php

declare(strict_types=1);

namespace App\Tests\Unit\Listener;

use App\Listener\ServerListener;
use App\Tests\TestCase;
use Lsp\Contracts\Server\ServerInterface;
use Lsp\Server\Event\Server\ServerStarted;
use Psr\Log\LoggerInterface;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\TestDox;

#[Group('unit')]
final class ServerListenerTest extends TestCase
{
    #[TestDox('logs server address and event name')]
    public function testLogsServerEvent(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())->method('debug');

        $listener = new ServerListener($logger);

        $address = $this->createMock(\Lsp\Contracts\Server\AddressInterface::class);
        $server = $this->createMock(ServerInterface::class);
        $server->method('getAddress')->willReturn($address);
        $event = new ServerStarted($server);

        $listener($event);
    }
}

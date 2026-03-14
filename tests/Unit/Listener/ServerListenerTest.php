<?php

declare(strict_types=1);

namespace App\Tests\Unit\Listener;

use App\Listener\ServerListener;
use App\Tests\TestCase;
use Lsp\Contracts\Server\AddressInterface;
use Lsp\Contracts\Server\ServerInterface;
use Lsp\Server\Event\Server\ServerStarted;
use Psr\Log\LoggerInterface;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\TestDox;

#[Group('unit')]
final class ServerListenerTest extends TestCase
{
    #[TestDox('logs server started event')]
    public function testLogsServerStarted(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())->method('debug');

        $address = $this->createMock(AddressInterface::class);

        $server = $this->createMock(ServerInterface::class);
        $server->method('getAddress')->willReturn($address);

        $listener = new ServerListener($logger);
        $event = new ServerStarted($server);

        $listener($event);
    }
}

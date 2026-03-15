<?php

declare(strict_types=1);

namespace App\Tests\Unit\Listener;

use App\Listener\DebugServerListener;
use App\Module\Debug\DebugHttpServer;
use App\Tests\TestCase;
use Lsp\Contracts\Server\AddressInterface;
use Lsp\Contracts\Server\ServerInterface;
use Lsp\Server\Event\Server\ServerStarted;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\TestDox;
use Psr\Log\LoggerInterface;

#[Group('unit')]
final class DebugServerListenerTest extends TestCase
{
    #[TestDox('resolves debug port as LSP port + 1')]
    public function testResolvesPortFromLsp(): void
    {
        $debugServer = $this->createMock(DebugHttpServer::class);
        $debugServer->expects($this->once())
            ->method('start')
            ->with('127.0.0.1', 5008);

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())->method('info');

        $listener = new DebugServerListener($debugServer, $logger);
        $listener($this->createServerStartedEvent('tcp://127.0.0.1:5007'));
    }

    #[TestDox('uses LSP_DEBUG_PORT env variable when set')]
    public function testUsesEnvPort(): void
    {
        putenv('LSP_DEBUG_PORT=9999');

        try {
            $debugServer = $this->createMock(DebugHttpServer::class);
            $debugServer->expects($this->once())
                ->method('start')
                ->with('127.0.0.1', 9999);

            $logger = $this->createMock(LoggerInterface::class);

            $listener = new DebugServerListener($debugServer, $logger);
            $listener($this->createServerStartedEvent('tcp://127.0.0.1:5007'));
        } finally {
            putenv('LSP_DEBUG_PORT');
        }
    }

    #[TestDox('falls back to 8081 when port cannot be parsed')]
    public function testFallbackPort(): void
    {
        $debugServer = $this->createMock(DebugHttpServer::class);
        $debugServer->expects($this->once())
            ->method('start')
            ->with('127.0.0.1', 8081);

        $logger = $this->createMock(LoggerInterface::class);

        $listener = new DebugServerListener($debugServer, $logger);
        $listener($this->createServerStartedEvent('unix:///tmp/lsp.sock'));
    }

    #[TestDox('logs debug server address')]
    public function testLogsAddress(): void
    {
        $debugServer = $this->createMock(DebugHttpServer::class);

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())
            ->method('info')
            ->with(
                $this->stringContains('Debug HTTP server started'),
                $this->callback(fn(array $ctx) => $ctx['host'] === '127.0.0.1' && $ctx['port'] === 5008),
            );

        $listener = new DebugServerListener($debugServer, $logger);
        $listener($this->createServerStartedEvent('tcp://127.0.0.1:5007'));
    }

    private function createServerStartedEvent(string $address): ServerStarted
    {
        $addressObj = $this->createMock(AddressInterface::class);
        $addressObj->method('__toString')->willReturn($address);

        $server = $this->createMock(ServerInterface::class);
        $server->method('getAddress')->willReturn($addressObj);

        return new ServerStarted($server);
    }
}

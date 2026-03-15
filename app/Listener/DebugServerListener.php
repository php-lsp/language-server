<?php

declare(strict_types=1);

namespace App\Listener;

use App\Module\Debug\DebugHttpServer;
use Lsp\Server\Event\Server\ServerStarted;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

#[AsEventListener]
final class DebugServerListener
{
    public function __construct(
        private readonly DebugHttpServer $debugServer,
        private readonly LoggerInterface $logger,
    ) {}

    public function __invoke(ServerStarted $event): void
    {
        $lspAddress = (string) $event->server->getAddress();

        // Parse port from LSP address (e.g. "tcp://127.0.0.1:5007")
        $host = '127.0.0.1';
        $debugPort = $this->resolveDebugPort($lspAddress);

        $this->debugServer->start($host, $debugPort);

        $this->logger->info('Debug HTTP server started at http://{host}:{port}', [
            'host' => $host,
            'port' => $debugPort,
        ]);
    }

    private function resolveDebugPort(string $lspAddress): int
    {
        // ENV takes priority
        $envPort = getenv('LSP_DEBUG_PORT');

        if ($envPort !== false && $envPort !== '') {
            return (int) $envPort;
        }

        // Default: LSP port + 1
        $parsed = parse_url($lspAddress);

        if (isset($parsed['port'])) {
            return $parsed['port'] + 1;
        }

        return 8081;
    }
}

<?php

declare(strict_types=1);

namespace App\Controller\TextDocument;

use Lsp\Dispatcher\Result\Provider\ResultProviderInterface;
use Lsp\Kernel\Attribute\AsController;
use Lsp\Protocol\Type\PublishDiagnosticsParams;
use Lsp\Router\Attribute\Route;
use Lsp\Rpc\Message\Notification;
use Lsp\Server\ConnectionProviderInterface;

#[AsController, Route('textDocument/publishDiagnostics')]
final class PublishDiagnosticsController
{
    public function __construct(
        private ResultProviderInterface $resultProvider,
        private ConnectionProviderInterface $connectionProvider,
    )
    {
    }

    public function __invoke(PublishDiagnosticsParams $message): void
    {
//        dump('textDocument/publishDiagnostics:', $message);

        $parameters = $this->resultProvider->getResult($message);

        $notification = new Notification(
            method: 'textDocument/publishDiagnostics',
            parameters: $parameters,
        );
        foreach ($this->connectionProvider->connections as $connection) {
            $connection->notify($notification);
        }
    }
}

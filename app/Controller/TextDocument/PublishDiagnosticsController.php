<?php

declare(strict_types=1);

namespace App\Controller\TextDocument;

use App\Module\Notification\ServerNotificationSender;
use Lsp\Kernel\Attribute\AsController;
use Lsp\Protocol\Type\PublishDiagnosticsParams;
use Lsp\Router\Attribute\Route;

#[AsController, Route('textDocument/publishDiagnostics')]
final class PublishDiagnosticsController
{
    public function __construct(
        private ServerNotificationSender $notificationSender,
    )
    {
    }

    public function __invoke(PublishDiagnosticsParams $message): void
    {
        $this->notificationSender->sendRawNotification('textDocument/publishDiagnostics', $message);
    }
}

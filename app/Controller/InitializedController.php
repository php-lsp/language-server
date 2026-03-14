<?php

declare(strict_types=1);

namespace App\Controller;

use App\Module\Notification\ServerNotificationSender;
use Lsp\Kernel\Attribute\AsController;
use Lsp\Protocol\Type\InitializedParams;
use Lsp\Router\Attribute\Route;
use Psr\Log\LoggerInterface;

#[AsController, Route('initialized')]
final class InitializedController
{
    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly ServerNotificationSender $notificationSender,
    ) {}

    public function __invoke(InitializedParams $initialized): void
    {
        $this->logger->info('LSP is initialized');
        $this->notificationSender->showMessage('LSP is initialized');
    }
}

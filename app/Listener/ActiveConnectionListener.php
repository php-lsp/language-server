<?php

declare(strict_types=1);

namespace App\Listener;

use App\Module\Notification\ActiveConnectionProvider;
use Lsp\Server\Event\Message\MessageReceived;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

#[AsEventListener]
final class ActiveConnectionListener
{
    public function __construct(
        private readonly ActiveConnectionProvider $connectionProvider,
    ) {}

    public function __invoke(MessageReceived $event): void
    {
        $this->connectionProvider->set($event->connection);
    }
}

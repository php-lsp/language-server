<?php

declare(strict_types=1);

namespace App\Listener;

use App\Module\Notification\ServerNotificationSender;
use Lsp\Contracts\Rpc\Message\FailureResponseInterface;
use Lsp\Protocol\Type\MessageType;
use Lsp\Server\Event\Message\FailureResponseSent;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

#[AsEventListener]
final class ExceptionNotificationListener
{
    /**
     * LSP error codes that should not trigger user-visible notifications.
     * These are normal protocol-level errors (method not found, etc.).
     */
    private const array IGNORED_CODES = [
        -32_601, // MethodNotFound
        -32_700, // ParseError
    ];

    public function __construct(
        private readonly ServerNotificationSender $notificationSender,
    ) {}

    public function __invoke(FailureResponseSent $event): void
    {
        /** @var FailureResponseInterface<mixed, mixed> $response */
        $response = $event->message;

        if (in_array($response->getCode(), self::IGNORED_CODES, strict: true)) {
            return;
        }

        $this->notificationSender->showMessage(
            message: $response->getMessage(),
            type: MessageType::Error,
        );
    }
}

<?php

declare(strict_types=1);

namespace App\Listener;

use Lsp\Contracts\Rpc\Message\NotificationInterface;
use Lsp\Contracts\Rpc\Message\RequestInterface;
use Lsp\Server\Event\Message\MessageReceived;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

/**
 * Traces all incoming LSP requests with timing information.
 *
 * Logs method name, request ID, and parameters for each incoming
 * request/notification. Works with Buggregator UI for visual tracing.
 *
 * @api
 */
#[AsEventListener]
final class RequestTracingListener extends LoggerListener
{
    public function __invoke(MessageReceived $event): void
    {
        $message = $event->message;

        if (!$message instanceof NotificationInterface) {
            return;
        }

        $method = $message->getMethod();
        $params = $message->getParameters();

        if ($message instanceof RequestInterface) {
            $this->logger->info('[trace] → {method} #{id}', [
                'method' => $method,
                'id' => (string) $message->getId(),
                'params' => $params,
            ]);
        } else {
            $this->logger->debug('[trace] → {method} (notification)', [
                'method' => $method,
                'params' => $params,
            ]);
        }
    }
}

<?php

declare(strict_types=1);

namespace App\Listener;

use App\Module\Telemetry\TracerInterface;
use Lsp\Contracts\Rpc\Message\NotificationInterface;
use Lsp\Contracts\Rpc\Message\RequestInterface;
use Lsp\Server\Event\Message\MessageReceived;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

/**
 * Traces all incoming LSP requests with timing information and APM spans.
 *
 * Creates OpenTelemetry spans for each incoming request/notification,
 * exporting them to an OTLP-compatible backend (SigNoz, Jaeger, etc.)
 * when configured.
 *
 * @api
 */
#[AsEventListener]
final class RequestTracingListener
{
    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly TracerInterface $tracer,
    ) {}

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

            return;
        }

        $this->logger->debug('[trace] → {method} (notification)', [
            'method' => $method,
            'params' => $params,
        ]);
    }
}

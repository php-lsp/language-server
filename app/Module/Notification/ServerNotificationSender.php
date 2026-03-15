<?php

declare(strict_types=1);

namespace App\Module\Notification;

use Lsp\Dispatcher\Result\Provider\ResultProviderInterface;
use Lsp\Protocol\Type\MessageType;
use Lsp\Protocol\Type\ShowMessageParams;
use Lsp\Rpc\Message\Notification;
use Psr\Log\LoggerInterface;

final class ServerNotificationSender
{
    public function __construct(
        private readonly ActiveConnectionProvider $connectionProvider,
        private readonly ResultProviderInterface $resultProvider,
        private readonly LoggerInterface $logger,
    ) {}

    public function showMessage(string $message, MessageType $type = MessageType::Info): Result
    {
        $params = new ShowMessageParams(
            type: $type,
            message: $message,
        );

        return $this->sendRawNotification(
            method: 'window/showMessage',
            parameters: $params,
        );
    }

    public function sendRawNotification(string $method, array|object|null $parameters): Result
    {
        $connection = $this->connectionProvider->get();

        if ($connection === null) {
            $this->logger->error('No active connection available');

            return Result::error('No active connection available');
        }

        $parameters = $this->resultProvider->getResult($parameters);

        $notification = new Notification($method, $parameters);

        try {
            $connection->notify($notification);

            $this->logger->debug('Sent notification: {method}', ['method' => $method]);

            return Result::success();
        } catch (\Throwable $e) {
            $this->logger->error('Failed to send notification: {message}', ['message' => $e->getMessage()]);

            return Result::error($e->getMessage());
        }
    }
}

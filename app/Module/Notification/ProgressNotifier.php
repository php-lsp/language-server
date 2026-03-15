<?php

declare(strict_types=1);

namespace App\Module\Notification;

use App\Core\Contracts\Notification\ProgressNotifierInterface;
use Override;

final class ProgressNotifier implements ProgressNotifierInterface
{
    public function __construct(
        private readonly ServerNotificationSender $sender,
    ) {}

    #[Override]
    public function create(#[\SensitiveParameter] string $token): void
    {
        $this->sender->sendRawNotification(
            method: 'window/workDoneProgress/create',
            parameters: ['token' => $token],
        );
    }

    #[Override]
    public function begin(
        #[\SensitiveParameter]
        string $token,
        string $title,
        ?string $message = null,
        ?int $percentage = null,
        ?bool $cancellable = null,
    ): void {
        $this->sender->sendRawNotification(
            method: '$/progress',
            parameters: [
                'token' => $token,
                'value' => \array_filter(
                    [
                        'kind' => 'begin',
                        'title' => $title,
                        'cancellable' => $cancellable,
                        'message' => $message,
                        'percentage' => $this->clampPercentage($percentage),
                    ],
                    static fn(mixed $v): bool => $v !== null,
                ),
            ],
        );
    }

    #[Override]
    public function report(
        #[\SensitiveParameter]
        string $token,
        ?string $message = null,
        ?int $percentage = null,
        ?bool $cancellable = null,
    ): void {
        $this->sender->sendRawNotification(
            method: '$/progress',
            parameters: [
                'token' => $token,
                'value' => \array_filter(
                    [
                        'kind' => 'report',
                        'cancellable' => $cancellable,
                        'message' => $message,
                        'percentage' => $this->clampPercentage($percentage),
                    ],
                    static fn(mixed $v): bool => $v !== null,
                ),
            ],
        );
    }

    /**
     * @return int<0, 100>|null
     */
    private function clampPercentage(?int $percentage): ?int
    {
        if ($percentage === null) {
            return null;
        }

        /** @var int<0, 100> */
        return max(0, min(100, $percentage));
    }

    #[Override]
    public function end(#[\SensitiveParameter] string $token, ?string $message = null): void
    {
        $this->sender->sendRawNotification(
            method: '$/progress',
            parameters: \array_filter([
                'token' => $token,
                'value' => \array_filter(
                    [
                        'kind' => 'end',
                        'message' => $message,
                    ],
                    static fn(mixed $v): bool => $v !== null,
                ),
            ]),
        );
    }
}

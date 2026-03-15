<?php

declare(strict_types=1);

namespace App\Module\Notification;

use Lsp\Protocol\Type\ProgressParams;
use Lsp\Protocol\Type\WorkDoneProgressBegin;
use Lsp\Protocol\Type\WorkDoneProgressCreateParams;
use Lsp\Protocol\Type\WorkDoneProgressEnd;
use Lsp\Protocol\Type\WorkDoneProgressReport;

final class ProgressNotifier
{
    public function __construct(
        private readonly ServerNotificationSender $sender,
    ) {}

    public function create(#[\SensitiveParameter] string $token): Result
    {
        return $this->sender->sendRawNotification(
            method: 'window/workDoneProgress/create',
            parameters: new WorkDoneProgressCreateParams(token: $token),
        );
    }

    public function begin(
        #[\SensitiveParameter]
        string $token,
        string $title,
        ?string $message = null,
        ?int $percentage = null,
        ?bool $cancellable = null,
    ): Result {
        return $this->sender->sendRawNotification(
            method: '$/progress',
            parameters: new ProgressParams(
                token: $token,
                value: new WorkDoneProgressBegin(
                    kind: 'begin',
                    title: $title,
                    cancellable: $cancellable,
                    message: $message,
                    percentage: $this->clampPercentage($percentage),
                ),
            ),
        );
    }

    public function report(
        #[\SensitiveParameter]
        string $token,
        ?string $message = null,
        ?int $percentage = null,
        ?bool $cancellable = null,
    ): Result {
        return $this->sender->sendRawNotification(
            method: '$/progress',
            parameters: new ProgressParams(
                token: $token,
                value: new WorkDoneProgressReport(
                    kind: 'report',
                    cancellable: $cancellable,
                    message: $message,
                    percentage: $this->clampPercentage($percentage),
                ),
            ),
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

    public function end(#[\SensitiveParameter] string $token, ?string $message = null): Result
    {
        return $this->sender->sendRawNotification(
            method: '$/progress',
            parameters: new ProgressParams(
                token: $token,
                value: new WorkDoneProgressEnd(
                    kind: 'end',
                    message: $message,
                ),
            ),
        );
    }
}

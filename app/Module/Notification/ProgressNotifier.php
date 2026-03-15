<?php

declare(strict_types=1);

namespace App\Module\Notification;

use App\Core\Contracts\Notification\ProgressNotifierInterface;
use Lsp\Protocol\Type\ProgressParams;
use Lsp\Protocol\Type\WorkDoneProgressBegin;
use Lsp\Protocol\Type\WorkDoneProgressCreateParams;
use Lsp\Protocol\Type\WorkDoneProgressEnd;
use Lsp\Protocol\Type\WorkDoneProgressReport;
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
            parameters: new WorkDoneProgressCreateParams(token: $token),
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

    #[Override]
    public function end(#[\SensitiveParameter] string $token, ?string $message = null): void
    {
        $this->sender->sendRawNotification(
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

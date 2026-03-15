<?php

declare(strict_types=1);

namespace App\Core\Contracts\Notification;

interface ProgressNotifierInterface
{
    public function create(#[\SensitiveParameter] string $token): void;

    public function begin(
        #[\SensitiveParameter]
        string $token,
        string $title,
        ?string $message = null,
        ?int $percentage = null,
        ?bool $cancellable = null,
    ): void;

    public function report(
        #[\SensitiveParameter]
        string $token,
        ?string $message = null,
        ?int $percentage = null,
        ?bool $cancellable = null,
    ): void;

    public function end(#[\SensitiveParameter] string $token, ?string $message = null): void;
}

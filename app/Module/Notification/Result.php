<?php

declare(strict_types=1);

namespace App\Module\Notification;

/**
 * Notification send result.
 */
final class Result
{
    private function __construct(
        public readonly bool $success,
        public readonly ?string $error = null,
    ) {}

    public static function success(): self
    {
        return new self(success: true);
    }

    public static function error(string $message): self
    {
        return new self(success: false, error: $message);
    }

    public function isSuccess(): bool
    {
        return $this->success;
    }

    public function isError(): bool
    {
        return !$this->success;
    }

    public function getError(): ?string
    {
        return $this->error;
    }
}

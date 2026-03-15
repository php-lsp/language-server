<?php

declare(strict_types=1);

namespace App\Core\Cancellation;

final class CancellationTokenRegistry
{
    /**
     * @var array<int|string, CancellationToken>
     */
    private array $tokens = [];

    public function create(int|string $requestId): CancellationToken
    {
        $token = new CancellationToken();
        $this->tokens[$requestId] = $token;

        return $token;
    }

    public function cancel(int|string $requestId): void
    {
        if (array_key_exists($requestId, $this->tokens)) {
            $this->tokens[$requestId]->cancel();
        }
    }

    public function remove(int|string $requestId): void
    {
        unset($this->tokens[$requestId]);
    }
}

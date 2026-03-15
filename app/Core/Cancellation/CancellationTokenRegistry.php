<?php

declare(strict_types=1);

namespace App\Core\Cancellation;

final class CancellationTokenRegistry
{
    private const int MAX_TOKENS = 1000;

    /**
     * @var array<int|string, CancellationToken>
     */
    private array $tokens = [];

    public function create(int|string $requestId): CancellationToken
    {
        if (count($this->tokens) >= self::MAX_TOKENS) {
            $this->evictOldest();
        }

        $token = new CancellationToken();
        $this->tokens[$requestId] = $token;

        return $token;
    }

    public function cancel(int|string $requestId): void
    {
        if (array_key_exists($requestId, $this->tokens)) {
            $this->tokens[$requestId]->cancel();
            unset($this->tokens[$requestId]);
        }
    }

    public function remove(int|string $requestId): void
    {
        unset($this->tokens[$requestId]);
    }

    public function count(): int
    {
        return count($this->tokens);
    }

    private function evictOldest(): void
    {
        $evictCount = (int) (self::MAX_TOKENS * 0.1);
        $keys = array_slice(array_keys($this->tokens), offset: 0, length: max(1, $evictCount));
        foreach ($keys as $key) {
            unset($this->tokens[$key]);
        }
    }
}

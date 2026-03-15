<?php

declare(strict_types=1);

namespace App\Module\PsiFile;

/**
 * FIFO Cache with fixed window size
 *
 * @template TElement
 */
final class FifoCache
{
    /** @var array<string, TElement> */
    private array $cache = [];

    /** @var list<string> */
    private array $queue = [];

    public function __construct(
        private readonly int $maxSize,
        private readonly float $evictionPercent = 0.1,
    ) {}

    /**
     * @return TElement|null
     */
    public function get(string $key): mixed
    {
        return $this->cache[$key] ?? null;
    }

    /**
     * @param TElement $value
     */
    public function set(string $key, mixed $value): void
    {
        if (array_key_exists($key, $this->cache)) {
            $this->cache[$key] = $value;

            return;
        }

        if (count($this->cache) >= $this->maxSize) {
            $this->evict();
        }

        $this->cache[$key] = $value;
        $this->queue[] = $key;
    }

    /**
     * Store a value that is excluded from FIFO eviction.
     *
     * @param TElement $value
     */
    public function setPermanent(string $key, mixed $value): void
    {
        if (array_key_exists($key, $this->cache)) {
            $this->cache[$key] = $value;

            return;
        }

        if (count($this->cache) >= $this->maxSize) {
            $this->evict();
        }

        $this->cache[$key] = $value;
    }

    public function has(string $key): bool
    {
        return array_key_exists($key, $this->cache);
    }

    public function remove(string $key): void
    {
        unset($this->cache[$key]);
        $index = array_search($key, $this->queue, strict: true);
        if ($index !== false) {
            array_splice($this->queue, $index, 1);
        }
    }

    public function clear(): void
    {
        $this->cache = [];
        $this->queue = [];
    }

    public function size(): int
    {
        return count($this->cache);
    }

    /**
     * Delete N% the oldest elements (FIFO)
     */
    private function evict(): void
    {
        $minimumEviction = 1;
        $evictCount = max($minimumEviction, (int) ceil($this->maxSize * $this->evictionPercent));

        $keysToRemove = array_slice($this->queue, offset: 0, length: $evictCount);

        foreach ($keysToRemove as $key) {
            unset($this->cache[$key]);
        }

        $this->queue = array_slice($this->queue, $evictCount);
    }
}

<?php

declare(strict_types=1);

namespace App\Index;

/**
 * Represents a named in-memory index that stores key-value data.
 *
 * @template TKey of array-key
 * @template TValue
 * @template-extends \Traversable<TKey, TValue>
 */
interface IndexInterface extends \Traversable, \Countable
{
    /**
     * Unique name of this index (e.g. "classes", "functions", "symbols").
     *
     * @return non-empty-string
     */
    public function getName(): string;

    /**
     * Check if the index contains an entry with the given key.
     *
     * @param TKey $key
     */
    public function has(int|string $key): bool;

    /**
     * Get a single entry by key.
     *
     * @param TKey $key
     * @return TValue|null
     */
    public function get(int|string $key): mixed;

    /**
     * Search entries by a callback filter.
     *
     * @param \Closure(TValue, TKey): bool $predicate
     * @return array<TKey, TValue>
     */
    public function filter(\Closure $predicate): array;

    /**
     * Return all keys in the index.
     *
     * @return list<TKey>
     */
    public function keys(): array;

    /**
     * Return a debug-friendly representation of an entry.
     *
     * @param TKey $key
     * @return array<string, mixed>|null
     */
    public function inspect(int|string $key): ?array;

    /**
     * Return debug summary of the entire index.
     *
     * @return array{name: non-empty-string, count: int<0, max>, keys: list<TKey>}
     */
    public function summary(): array;
}

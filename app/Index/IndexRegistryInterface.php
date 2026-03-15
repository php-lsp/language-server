<?php

declare(strict_types=1);

namespace App\Index;

/**
 * Central registry for all in-memory indexes.
 * Provides debug access to search, inspect and list indexes.
 */
interface IndexRegistryInterface extends \Countable
{
    /**
     * Register an index in the registry.
     *
     * @param IndexInterface<array-key, mixed> $index
     */
    public function register(IndexInterface $index): void;

    /**
     * Get an index by name.
     *
     * @param non-empty-string $name
     * @return IndexInterface<array-key, mixed>|null
     */
    public function get(string $name): ?IndexInterface;

    /**
     * List all registered index names.
     *
     * @return list<non-empty-string>
     */
    public function names(): array;

    /**
     * Get a summary of all indexes (name, count, keys).
     *
     * @return list<array{name: non-empty-string, count: int<0, max>, keys: list<array-key>}>
     */
    public function summaries(): array;

    /**
     * Search across all indexes for entries matching a key pattern.
     *
     * @return array<non-empty-string, array<array-key, array<string, mixed>>>
     */
    public function search(string $pattern): array;
}

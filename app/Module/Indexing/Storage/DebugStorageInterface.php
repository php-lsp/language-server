<?php

declare(strict_types=1);

namespace App\Module\Indexing\Storage;

/**
 * Extended storage interface with debug introspection capabilities.
 *
 * Allows manual inspection of index contents at runtime:
 * listing registered index keys, counting entries, searching by key patterns.
 */
interface DebugStorageInterface extends StorageInterface
{
    /**
     * List all index keys that have been written to.
     *
     * @return list<string>
     */
    public function getIndexKeys(): array;

    /**
     * Count entries in a specific index.
     */
    public function count(string $indexKey): int;

    /**
     * Find entries by key pattern (fnmatch glob).
     *
     * @return iterable<Entry>
     */
    public function search(string $indexKey, string $keyPattern): iterable;

    /**
     * Get a single entry by exact key within an index.
     */
    public function find(string $indexKey, string $entryKey): ?Entry;

    /**
     * Estimate memory usage of a specific index in bytes.
     */
    public function memoryUsage(string $indexKey): int;

    /**
     * Get summary stats for all indexes.
     *
     * @return array<string, array{key: string, count: int, memory: int}>
     */
    public function stats(): array;

    /**
     * Search across all indexes by key pattern.
     *
     * @return iterable<array{index: string, key: string, value: mixed, uri: string}>
     */
    public function searchAll(string $keyPattern, int $limit = 100): iterable;

    /**
     * Clear all entries from specific indexes.
     *
     * @param list<string> $indexKeys
     */
    public function clear(array $indexKeys): void;

    /**
     * Remove all entries associated with a specific URI across all indexes.
     */
    public function clearByUri(string $uri): void;

    /**
     * Get all unique URIs across all indexes.
     *
     * @return list<string>
     */
    public function getUris(): array;

    /**
     * Find all entries across all indexes that belong to a specific URI.
     *
     * @return array<string, list<Entry>>  indexKey => list of entries
     */
    public function findByUri(string $uri): array;
}

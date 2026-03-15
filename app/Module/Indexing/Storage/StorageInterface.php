<?php

declare(strict_types=1);

namespace App\Module\Indexing\Storage;

interface StorageInterface
{
    /**
     * @return iterable<string, Entry>
     */
    public function read(string $indexKey): iterable;

    public function find(string $indexKey, string $entryKey): ?Entry;

    /**
     * @return iterable<Entry>
     */
    public function readByField(string $indexKey, string $field, string $value): iterable;

    public function write(string $indexKey, array $values, string $uri): void;

    public function deleteByUri(string $uri): void;
}

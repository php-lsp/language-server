<?php

namespace App\Module\Indexing\Storage;

interface StorageInterface
{
    /**
     * @return iterable<string, Entry>
     */
    public function read(string $indexKey): iterable;

    public function write(string $indexKey, array $values, string $uri): void;
}

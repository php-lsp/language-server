<?php

declare(strict_types=1);

namespace App\Module\Indexing;

use App\Core\Contracts\Indexing\IndexerInterface;
use App\Module\Indexing\Storage\Entry;
use App\Module\Indexing\Storage\StorageInterface;

final class IndexLookup
{
    public function __construct(
        private readonly StorageInterface $storage,
    ) {}

    /**
     * @template T
     *
     * @param class-string<IndexerInterface<T>> $indexerClass
     *
     * @return iterable<Entry<T>>
     */
    public function findByKey(string $indexerClass): iterable
    {
        foreach ($this->storage->read($indexerClass::getKey()) as $key => $entry) {
            yield $key => $entry;
        }
    }

    /**
     * @template T
     *
     * @param class-string<IndexerInterface<T>> $indexerClass
     *
     * @return Entry<T>|null
     */
    public function findEntry(string $indexerClass, string $key): ?Entry
    {
        return $this->storage->find($indexerClass::getKey(), $key);
    }

    /**
     * @template T
     *
     * @param class-string<IndexerInterface<T>> $indexerClass
     *
     * @return iterable<Entry<T>>
     */
    public function findByField(string $indexerClass, string $field, string $value): iterable
    {
        return $this->storage->readByField($indexerClass::getKey(), $field, $value);
    }
}

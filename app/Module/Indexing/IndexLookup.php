<?php

namespace App\Module\Indexing;

use App\Module\Indexing\Storage\Entry;
use App\Module\Indexing\Storage\StorageInterface;

final class IndexLookup
{
    public function __construct(
        private readonly StorageInterface $storage,
    )
    {
    }

    /**
     * @template T
     * @param class-string<IndexerInterface<T>> $indexerClass
     * @return iterable<Entry<T>>
     */
    public function findByKey(string $indexerClass): iterable
    {
        foreach ($this->storage->read($indexerClass::getKey()) as $key => $entry) {
            yield $key => $entry;
        }
    }
}

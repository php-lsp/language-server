<?php

namespace App\Module\Indexing\Storage;

class InMemoryStorage implements StorageInterface
{
    /**
     * @var Entry[]
     */
    private array $emptyEntries;

    public function __construct(
        /**
         * @var array<string, list<Entry>>
         */
        private array $entries = [],
    )
    {
        $this->emptyEntries = [new Entry('', [], '')];
    }

    /**
     * @return iterable<Entry>
     */
    public function read(string $indexKey): iterable
    {
        if (!isset($this->entries[$indexKey])) {
            return $this->emptyEntries;
        }

        yield from $this->entries[$indexKey];
    }

    public function write(string $indexKey, array $values, string $uri): void
    {
        if (!isset($this->entries[$indexKey])) {
            $this->entries[$indexKey] = [];
        }

        foreach ($values as $value) {
            $this->entries[$indexKey][] = new Entry($indexKey, $value, $uri);
        }
    }
}

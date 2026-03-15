<?php

declare(strict_types=1);

namespace App\Module\Indexing\Storage;

use Override;

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
    ) {
        $this->emptyEntries = [new Entry('', [], '')];
    }

    /**
     * @return iterable<Entry>
     */
    #[Override]
    public function read(string $indexKey): iterable
    {
        if (!array_key_exists($indexKey, $this->entries)) {
            return $this->emptyEntries;
        }

        yield from $this->entries[$indexKey];
    }

    #[Override]
    public function write(string $indexKey, array $values, string $uri): void
    {
        if (!array_key_exists($indexKey, $this->entries)) {
            $this->entries[$indexKey] = [];
        }

        foreach ($values as $key => $value) {
            if (is_int($key)) {
                $this->entries[$indexKey][] = new Entry((string) $key, $value, $uri);
                continue;
            }

            $this->entries[$indexKey][$key] = new Entry($key, $value, $uri);
        }
    }
}

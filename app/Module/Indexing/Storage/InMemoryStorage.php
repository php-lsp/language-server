<?php

declare(strict_types=1);

namespace App\Module\Indexing\Storage;

use Override;

class InMemoryStorage implements DebugStorageInterface
{
    /**
     * @var Entry[]
     */
    private array $emptyEntries;

    /**
     * Secondary indexes: indexKey -> fieldName -> fieldValue -> list<Entry>.
     *
     * @var array<string, array<string, array<string, list<Entry>>>>
     */
    private array $secondaryIndexes = [];

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

    /**
     * Lookup entries by a field value using a secondary index.
     *
     * On first call for a given indexKey+field combination, the secondary
     * index is built by iterating all entries once. Subsequent lookups
     * are O(1) hash-map access.
     *
     * @return iterable<Entry>
     */
    public function readByField(string $indexKey, string $field, string $value): iterable
    {
        if (!array_key_exists($indexKey . ':' . $field, $this->secondaryIndexes)) {
            $this->buildSecondaryIndex($indexKey, $field);
        }

        yield from $this->secondaryIndexes[$indexKey . ':' . $field][$value] ?? [];
    }

    #[Override]
    public function write(string $indexKey, array $values, string $uri): void
    {
        if (!array_key_exists($indexKey, $this->entries)) {
            $this->entries[$indexKey] = [];
        }

        foreach ($values as $key => $value) {
            $entry = new Entry(is_int($key) ? (string) $key : $key, $value, $uri);

            match (is_int($key)) {
                true => $this->entries[$indexKey][] = $entry,
                false => $this->entries[$indexKey][$key] = $entry,
            };

            $this->updateSecondaryIndexes($indexKey, $entry);
        }
    }

    public function getIndexKeys(): array
    {
        return \array_keys($this->entries);
    }

    public function count(string $indexKey): int
    {
        return array_key_exists($indexKey, $this->entries)
            ? \count($this->entries[$indexKey])
            : 0;
    }

    /**
     * @return iterable<Entry>
     */
    public function search(string $indexKey, string $keyPattern): iterable
    {
        if (!array_key_exists($indexKey, $this->entries)) {
            return;
        }

        foreach ($this->entries[$indexKey] as $entry) {
            if (\fnmatch($keyPattern, $entry->key, \FNM_CASEFOLD | \FNM_NOESCAPE)) {
                yield $entry;
            }
        }
    }

    public function find(string $indexKey, string $entryKey): ?Entry
    {
        if (!array_key_exists($indexKey, $this->entries)) {
            return null;
        }

        foreach ($this->entries[$indexKey] as $entry) {
            if ($entry->key === $entryKey) {
                return $entry;
            }
        }

        return null;
    }

    public function memoryUsage(string $indexKey): int
    {
        if (!array_key_exists($indexKey, $this->entries)) {
            return 0;
        }

        $before = memory_get_usage();
        $serialized = serialize($this->entries[$indexKey]);
        $after = memory_get_usage();

        // Use the serialized string length as a reasonable estimate
        return \strlen($serialized);
    }

    public function stats(): array
    {
        $result = [];

        foreach ($this->entries as $indexKey => $entries) {
            $result[$indexKey] = [
                'key' => $indexKey,
                'count' => \count($entries),
                'memory' => $this->memoryUsage($indexKey),
            ];
        }

        return $result;
    }

    /**
     * @return iterable<array{index: string, key: string, value: mixed, uri: string}>
     */
    public function searchAll(string $keyPattern, int $limit = 100): iterable
    {
        $count = 0;

        foreach ($this->entries as $indexKey => $entries) {
            foreach ($entries as $entry) {
                if ($count >= $limit) {
                    return;
                }

                if (\fnmatch($keyPattern, $entry->key, \FNM_CASEFOLD | \FNM_NOESCAPE)) {
                    yield [
                        'index' => $indexKey,
                        'key' => $entry->key,
                        'value' => $entry->value,
                        'uri' => $entry->uri,
                    ];
                    $count++;
                }
            }
        }
    }

    public function clear(array $indexKeys): void
    {
        foreach ($indexKeys as $indexKey) {
            unset($this->entries[$indexKey]);

            // Clear secondary indexes for this index
            foreach (array_keys($this->secondaryIndexes) as $compositeKey) {
                if (str_starts_with($compositeKey, $indexKey . ':')) {
                    unset($this->secondaryIndexes[$compositeKey]);
                }
            }
        }
    }

    private function updateSecondaryIndexes(string $indexKey, Entry $entry): void
    {
        foreach ($this->secondaryIndexes as $compositeKey => &$index) {
            if (!str_starts_with($compositeKey, $indexKey . ':')) {
                continue;
            }

            $field = substr($compositeKey, strlen($indexKey) + 1);
            $fieldValue = $this->extractField($entry, $field);
            if ($fieldValue !== null) {
                $index[$fieldValue][] = $entry;
            }
        }
        unset($index);
    }

    private function buildSecondaryIndex(string $indexKey, string $field): void
    {
        $compositeKey = $indexKey . ':' . $field;
        $this->secondaryIndexes[$compositeKey] = [];

        if (!array_key_exists($indexKey, $this->entries)) {
            return;
        }

        foreach ($this->entries[$indexKey] as $entry) {
            $fieldValue = $this->extractField($entry, $field);
            if ($fieldValue !== null) {
                $this->secondaryIndexes[$compositeKey][$fieldValue][] = $entry;
            }
        }
    }

    private function extractField(Entry $entry, string $field): ?string
    {
        $value = $entry->value;
        if (is_object($value) && property_exists($value, $field)) {
            $fieldValue = $value->$field;

            return is_string($fieldValue) ? $fieldValue : null;
        }

        return null;
    }
}

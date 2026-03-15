<?php

declare(strict_types=1);

namespace App\Index;

/**
 * @implements \IteratorAggregate<non-empty-string, IndexInterface<array-key, mixed>>
 */
final class IndexRegistry implements IndexRegistryInterface, \IteratorAggregate
{
    /** @var array<non-empty-string, IndexInterface<array-key, mixed>> */
    private array $indexes = [];

    /**
     * @param IndexInterface<array-key, mixed> $index
     */
    public function register(IndexInterface $index): void
    {
        $this->indexes[$index->getName()] = $index;
    }

    /**
     * @return IndexInterface<array-key, mixed>|null
     */
    public function get(string $name): ?IndexInterface
    {
        return $this->indexes[$name] ?? null;
    }

    public function names(): array
    {
        return \array_keys($this->indexes);
    }

    public function summaries(): array
    {
        return \array_values(\array_map(
            static fn(IndexInterface $index): array => $index->summary(),
            $this->indexes,
        ));
    }

    public function search(string $pattern): array
    {
        $results = [];

        foreach ($this->indexes as $index) {
            $matches = [];

            foreach ($index->keys() as $key) {
                if (\fnmatch($pattern, (string) $key, \FNM_CASEFOLD)) {
                    $inspected = $index->inspect($key);
                    if ($inspected !== null) {
                        $matches[$key] = $inspected;
                    }
                }
            }

            if ($matches !== []) {
                $results[$index->getName()] = $matches;
            }
        }

        return $results;
    }

    public function count(): int
    {
        return \count($this->indexes);
    }

    public function getIterator(): \Traversable
    {
        yield from $this->indexes;
    }
}

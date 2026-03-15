<?php

declare(strict_types=1);

namespace App\Index;

/**
 * Generic in-memory index backed by a plain array.
 *
 * @template TKey of array-key
 * @template TValue
 * @implements MutableIndexInterface<TKey, TValue>
 * @implements \IteratorAggregate<TKey, TValue>
 */
class InMemoryIndex implements MutableIndexInterface, \IteratorAggregate
{
    /** @var array<TKey, TValue> */
    private array $data = [];

    /**
     * @param non-empty-string $name
     * @param (\Closure(TValue): array<string, mixed>)|null $inspector
     */
    public function __construct(
        private readonly string $name,
        private readonly ?\Closure $inspector = null,
    ) {}

    public function getName(): string
    {
        return $this->name;
    }

    public function has(int|string $key): bool
    {
        return \array_key_exists($key, $this->data);
    }

    public function get(int|string $key): mixed
    {
        return $this->data[$key] ?? null;
    }

    public function set(int|string $key, mixed $value): void
    {
        $this->data[$key] = $value;
    }

    public function remove(int|string $key): void
    {
        unset($this->data[$key]);
    }

    public function clear(): void
    {
        $this->data = [];
    }

    public function filter(\Closure $predicate): array
    {
        return \array_filter($this->data, $predicate, \ARRAY_FILTER_USE_BOTH);
    }

    public function keys(): array
    {
        return \array_keys($this->data);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function inspect(int|string $key): ?array
    {
        if (!$this->has($key)) {
            return null;
        }

        $value = $this->data[$key];

        if ($this->inspector !== null) {
            return ($this->inspector)($value);
        }

        return $this->defaultInspect($key, $value);
    }

    public function summary(): array
    {
        return [
            'name' => $this->name,
            'count' => $this->count(),
            'keys' => $this->keys(),
        ];
    }

    public function count(): int
    {
        return \count($this->data);
    }

    /**
     * @return \ArrayIterator<TKey, TValue>
     */
    public function getIterator(): \ArrayIterator
    {
        return new \ArrayIterator($this->data);
    }

    /**
     * @return array<string, mixed>
     */
    private function defaultInspect(int|string $key, mixed $value): array
    {
        $result = ['key' => $key, 'type' => \get_debug_type($value)];

        if (\is_scalar($value) || $value === null) {
            $result['value'] = $value;
        } elseif (\is_array($value)) {
            $result['value'] = $value;
        } elseif (\is_object($value)) {
            $result['class'] = $value::class;
            $result['value'] = $this->objectToArray($value);
        }

        return $result;
    }

    /**
     * @return array<string, mixed>
     */
    private function objectToArray(object $object): array
    {
        $result = [];

        $ref = new \ReflectionObject($object);

        foreach ($ref->getProperties() as $property) {
            $value = $property->getValue($object);
            $result[$property->getName()] = match (true) {
                \is_scalar($value), $value === null => $value,
                \is_array($value) => '(array[' . \count($value) . '])',
                \is_object($value) => '(' . $value::class . ')',
                default => '(' . \get_debug_type($value) . ')',
            };
        }

        return $result;
    }
}

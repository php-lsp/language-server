<?php

declare(strict_types=1);

namespace App\Index;

/**
 * @template TKey of array-key
 * @template TValue
 * @template-extends IndexInterface<TKey, TValue>
 */
interface MutableIndexInterface extends IndexInterface
{
    /**
     * @param TKey $key
     * @param TValue $value
     */
    public function set(int|string $key, mixed $value): void;

    /**
     * @param TKey $key
     */
    public function remove(int|string $key): void;

    public function clear(): void;
}

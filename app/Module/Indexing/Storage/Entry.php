<?php

declare(strict_types=1);

namespace App\Module\Indexing\Storage;

/**
 * @template TValue
 */
class Entry
{
    public function __construct(
        public readonly string $key,
        /**
         * @var TValue
         */
        public readonly mixed $value,
        public readonly string $uri,
    ) {}
}

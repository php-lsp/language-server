<?php

declare(strict_types=1);

namespace App\Module\Indexing\Storage\IndexData;

final readonly class TraitData
{
    public function __construct(
        public string $fqn,
        public int $startPosition,
        public int $endPosition,
    ) {}
}

<?php

declare(strict_types=1);

namespace App\Module\Indexing\Data;

final class TraitData
{
    public function __construct(
        public readonly string $fqn,
        public readonly int $startPosition,
        public readonly int $endPosition,
    ) {}
}

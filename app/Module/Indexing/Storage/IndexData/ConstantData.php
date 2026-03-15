<?php

declare(strict_types=1);

namespace App\Module\Indexing\Storage\IndexData;

final readonly class ConstantData
{
    public function __construct(
        public string $name,
        public ?string $ownerFqn,
        public int $startPosition,
        public int $endPosition,
        public ?string $type,
        public ?string $value,
    ) {}
}

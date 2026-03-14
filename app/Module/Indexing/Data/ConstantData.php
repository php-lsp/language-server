<?php

declare(strict_types=1);

namespace App\Module\Indexing\Data;

final class ConstantData
{
    public function __construct(
        public readonly string $name,
        public readonly ?string $className,
        public readonly int $startPosition,
        public readonly int $endPosition,
        public readonly ?string $type,
        public readonly ?Visibility $visibility,
    ) {}
}

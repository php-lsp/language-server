<?php

declare(strict_types=1);

namespace App\Module\Indexing\Data;

final class PropertyData
{
    public function __construct(
        public readonly string $name,
        public readonly string $className,
        public readonly int $startPosition,
        public readonly int $endPosition,
        public readonly Visibility $visibility,
        public readonly ?string $type,
        public readonly bool $isStatic,
        public readonly bool $isReadonly,
        public readonly bool $isPromoted,
    ) {}
}

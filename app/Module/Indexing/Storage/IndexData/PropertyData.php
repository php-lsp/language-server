<?php

declare(strict_types=1);

namespace App\Module\Indexing\Storage\IndexData;

final readonly class PropertyData
{
    public function __construct(
        public string $name,
        public string $className,
        public int $startPosition,
        public int $endPosition,
        public string $visibility,
        public ?string $type,
        public bool $isStatic,
        public bool $isReadonly,
    ) {}
}

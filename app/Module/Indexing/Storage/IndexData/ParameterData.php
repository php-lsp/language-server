<?php

declare(strict_types=1);

namespace App\Module\Indexing\Storage\IndexData;

final readonly class ParameterData
{
    public function __construct(
        public string $name,
        public ?string $type,
        public bool $hasDefault,
        public bool $isVariadic,
        public bool $isPromoted,
    ) {}
}

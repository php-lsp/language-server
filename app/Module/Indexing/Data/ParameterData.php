<?php

declare(strict_types=1);

namespace App\Module\Indexing\Data;

final class ParameterData
{
    public function __construct(
        public readonly string $name,
        public readonly ?string $type,
        public readonly bool $hasDefault,
        public readonly bool $isVariadic,
        public readonly bool $isPromoted,
        public readonly bool $isNullable,
    ) {}
}

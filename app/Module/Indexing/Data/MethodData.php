<?php

declare(strict_types=1);

namespace App\Module\Indexing\Data;

final class MethodData
{
    /**
     * @param list<ParameterData> $parameters
     */
    public function __construct(
        public readonly string $name,
        public readonly string $className,
        public readonly int $startPosition,
        public readonly int $endPosition,
        public readonly Visibility $visibility,
        public readonly bool $isStatic,
        public readonly bool $isAbstract,
        public readonly ?string $returnType,
        public readonly array $parameters,
    ) {}
}

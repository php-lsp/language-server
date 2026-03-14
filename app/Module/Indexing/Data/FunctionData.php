<?php

declare(strict_types=1);

namespace App\Module\Indexing\Data;

final class FunctionData
{
    /**
     * @param list<ParameterData> $parameters
     */
    public function __construct(
        public readonly string $fqn,
        public readonly int $startPosition,
        public readonly int $endPosition,
        public readonly ?string $returnType,
        public readonly array $parameters,
    ) {}
}

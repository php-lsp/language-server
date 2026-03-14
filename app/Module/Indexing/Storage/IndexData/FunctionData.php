<?php

declare(strict_types=1);

namespace App\Module\Indexing\Storage\IndexData;

final readonly class FunctionData
{
    /**
     * @param list<ParameterData> $parameters
     */
    public function __construct(
        public string $fqn,
        public int $startPosition,
        public int $endPosition,
        public array $parameters,
        public ?string $returnType,
    ) {}
}

<?php

declare(strict_types=1);

namespace App\Module\Indexing\Storage\IndexData;

final readonly class MethodData
{
    /**
     * @param list<ParameterData> $parameters
     */
    public function __construct(
        public string $name,
        public string $className,
        public int $startPosition,
        public int $endPosition,
        public string $visibility,
        public bool $isStatic,
        public bool $isAbstract,
        public array $parameters,
        public ?string $returnType,
    ) {}
}

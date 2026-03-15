<?php

declare(strict_types=1);

namespace App\Module\Indexing\Storage\IndexData;

final readonly class ClassData
{
    /**
     * @param list<string> $implements
     */
    public function __construct(
        public string $fqn,
        public int $startPosition,
        public int $endPosition,
        public ?string $extends,
        public array $implements,
        public bool $isAbstract,
        public bool $isFinal,
    ) {}
}

<?php

declare(strict_types=1);

namespace App\Module\Indexing\Data;

final class ClassData
{
    /**
     * @param list<string> $implements
     */
    public function __construct(
        public readonly string $fqn,
        public readonly int $startPosition,
        public readonly int $endPosition,
        public readonly bool $isAbstract,
        public readonly bool $isFinal,
        public readonly bool $isReadonly,
        public readonly ?string $extends,
        public readonly array $implements,
    ) {}
}

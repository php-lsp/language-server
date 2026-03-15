<?php

declare(strict_types=1);

namespace App\Module\Indexing\Data;

final class InterfaceData
{
    /**
     * @param list<string> $extends
     */
    public function __construct(
        public readonly string $fqn,
        public readonly int $startPosition,
        public readonly int $endPosition,
        public readonly array $extends,
    ) {}
}

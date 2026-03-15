<?php

declare(strict_types=1);

namespace App\Module\Indexing\Data;

final class InheritanceData
{
    /**
     * @param list<string> $parents
     */
    public function __construct(
        public readonly string $fqn,
        public readonly string $kind,
        public readonly array $parents,
    ) {}
}

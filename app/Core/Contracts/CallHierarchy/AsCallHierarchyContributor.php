<?php

declare(strict_types=1);

namespace App\Core\Contracts\CallHierarchy;

#[\Attribute(\Attribute::TARGET_CLASS)]
final class AsCallHierarchyContributor
{
    public function __construct(
        public readonly int $priority = 0,
    ) {}
}

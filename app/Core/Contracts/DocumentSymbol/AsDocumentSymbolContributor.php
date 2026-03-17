<?php

declare(strict_types=1);

namespace App\Core\Contracts\DocumentSymbol;

#[\Attribute(\Attribute::TARGET_CLASS)]
final class AsDocumentSymbolContributor
{
    public function __construct(
        public readonly int $priority = 0,
    ) {}
}

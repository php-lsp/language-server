<?php

declare(strict_types=1);

namespace App\Core\Contracts\TypeDefinition;

use Lsp\Protocol\Type\Location;

class TypeDefinitionConsumer
{
    public function __construct(
        /**
         * @var list<Location>
         */
        public array $results = [],
    ) {}

    public function __invoke(Location ...$items): void
    {
        array_push($this->results, ...$items);
    }
}

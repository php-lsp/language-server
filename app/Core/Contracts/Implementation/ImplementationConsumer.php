<?php

declare(strict_types=1);

namespace App\Core\Contracts\Implementation;

use Lsp\Protocol\Type\Location;

class ImplementationConsumer
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

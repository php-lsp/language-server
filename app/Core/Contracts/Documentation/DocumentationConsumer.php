<?php

namespace App\Core\Contracts\Documentation;

class DocumentationConsumer
{
    public function __construct(
        public array $results = [],
    )
    {
    }

    public function __invoke(string ...$items): void
    {
        array_push($this->results, ...$items);
    }
}

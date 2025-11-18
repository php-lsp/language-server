<?php

namespace App\Core\Contracts;

use Lsp\Protocol\Type\CompletionItem;

class CompletionConsumer
{
    public function __construct(
        public array $results = [],
    )
    {
    }

    public function __invoke(CompletionItem ...$items): void
    {
        array_push($this->results, ...$items);
    }
}

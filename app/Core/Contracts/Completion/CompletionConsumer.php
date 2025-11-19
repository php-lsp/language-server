<?php

namespace App\Core\Contracts\Completion;

use Lsp\Protocol\Type\CompletionItem;

class CompletionConsumer
{
    public function __construct(
        /**
         * @var list<CompletionItem>
         */
        public array $results = [],
    )
    {
    }

    public function __invoke(CompletionItem ...$items): void
    {
        array_push($this->results, ...$items);
    }
}

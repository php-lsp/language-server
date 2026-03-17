<?php

declare(strict_types=1);

namespace App\Core\Contracts\CallHierarchy;

use Lsp\Protocol\Type\CallHierarchyItem;

class PrepareCallHierarchyConsumer
{
    public function __construct(
        /**
         * @var list<CallHierarchyItem>
         */
        public array $results = [],
    ) {}

    public function __invoke(CallHierarchyItem ...$items): void
    {
        array_push($this->results, ...$items);
    }
}

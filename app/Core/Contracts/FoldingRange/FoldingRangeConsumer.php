<?php

declare(strict_types=1);

namespace App\Core\Contracts\FoldingRange;

use Lsp\Protocol\Type\FoldingRange;

class FoldingRangeConsumer
{
    public function __construct(
        /**
         * @var list<FoldingRange>
         */
        public array $results = [],
    ) {}

    public function __invoke(FoldingRange ...$items): void
    {
        array_push($this->results, ...$items);
    }
}

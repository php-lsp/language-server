<?php

declare(strict_types=1);

namespace App\Core\Contracts\SelectionRange;

use Lsp\Protocol\Type\SelectionRange;

class SelectionRangeConsumer
{
    public function __construct(
        /**
         * Results indexed by position index.
         *
         * @var array<int, SelectionRange>
         */
        public array $results = [],
    ) {}

    public function __invoke(int $positionIndex, SelectionRange $selectionRange): void
    {
        $this->results[$positionIndex] = $selectionRange;
    }
}

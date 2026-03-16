<?php

declare(strict_types=1);

namespace App\Core\Contracts\InlayHint;

use Lsp\Protocol\Type\InlayHint;

class InlayHintConsumer
{
    public function __construct(
        /**
         * @var list<InlayHint>
         */
        public array $results = [],
    ) {}

    public function __invoke(InlayHint ...$items): void
    {
        array_push($this->results, ...$items);
    }
}

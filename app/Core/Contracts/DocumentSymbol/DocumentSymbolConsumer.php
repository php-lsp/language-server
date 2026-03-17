<?php

declare(strict_types=1);

namespace App\Core\Contracts\DocumentSymbol;

use Lsp\Protocol\Type\DocumentSymbol;

class DocumentSymbolConsumer
{
    public function __construct(
        /**
         * @var list<DocumentSymbol>
         */
        public array $results = [],
    ) {}

    public function __invoke(DocumentSymbol ...$items): void
    {
        array_push($this->results, ...$items);
    }
}

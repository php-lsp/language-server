<?php

declare(strict_types=1);

namespace App\Core\Contracts\Highlight;

use Lsp\Protocol\Type\DocumentHighlight;

class DocumentHighlightConsumer
{
    public function __construct(
        /**
         * @var list<DocumentHighlight>
         */
        public array $results = [],
    ) {}

    public function __invoke(DocumentHighlight ...$items): void
    {
        array_push($this->results, ...$items);
    }
}

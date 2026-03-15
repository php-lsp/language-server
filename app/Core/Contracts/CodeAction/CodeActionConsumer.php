<?php

declare(strict_types=1);

namespace App\Core\Contracts\CodeAction;

use Lsp\Protocol\Type\CodeAction;

class CodeActionConsumer
{
    public function __construct(
        /**
         * @var list<CodeAction>
         */
        public array $results = [],
    ) {}

    public function __invoke(CodeAction ...$items): void
    {
        array_push($this->results, ...$items);
    }
}

<?php

declare(strict_types=1);

namespace App\Core\Contracts\SemanticToken;

use App\Module\SemanticToken\RawSemanticToken;

class SemanticTokenConsumer
{
    public function __construct(
        /**
         * @var list<RawSemanticToken>
         */
        public array $tokens = [],
    ) {}

    public function __invoke(RawSemanticToken ...$items): void
    {
        array_push($this->tokens, ...$items);
    }
}

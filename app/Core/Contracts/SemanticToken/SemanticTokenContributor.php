<?php

declare(strict_types=1);

namespace App\Core\Contracts\SemanticToken;

interface SemanticTokenContributor
{
    public function contribute(SemanticTokenContext $context, SemanticTokenConsumer $consumer): void;
}

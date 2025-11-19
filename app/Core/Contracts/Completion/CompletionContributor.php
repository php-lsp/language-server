<?php

namespace App\Core\Contracts\Completion;

interface CompletionContributor
{
    public function contribute(CompletionContext $context, CompletionConsumer $consumer): void;
}

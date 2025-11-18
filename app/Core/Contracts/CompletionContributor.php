<?php

namespace App\Core\Contracts;

interface CompletionContributor
{
    public function contribute(CompletionContext $context, CompletionConsumer $consumer): void;
}

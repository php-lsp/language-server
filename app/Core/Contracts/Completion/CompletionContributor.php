<?php
declare(strict_types=1);

namespace App\Core\Contracts\Completion;

interface CompletionContributor
{
    public function contribute(CompletionContext $context, CompletionConsumer $consumer): void;
}

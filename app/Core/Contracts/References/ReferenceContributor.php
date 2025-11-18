<?php

namespace App\Core\Contracts\References;

use App\Core\Contracts\CompletionConsumer;
use App\Core\Contracts\CompletionContext;

interface ReferenceContributor
{
    public function contribute(ReferenceContext $context, ReferenceConsumer $consumer): void;
}

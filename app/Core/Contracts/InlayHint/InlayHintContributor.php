<?php

declare(strict_types=1);

namespace App\Core\Contracts\InlayHint;

interface InlayHintContributor
{
    public function contribute(InlayHintContext $context, InlayHintConsumer $consumer): void;
}

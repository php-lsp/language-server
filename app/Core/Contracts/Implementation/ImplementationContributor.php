<?php

declare(strict_types=1);

namespace App\Core\Contracts\Implementation;

interface ImplementationContributor
{
    public function contribute(ImplementationContext $context, ImplementationConsumer $consumer): void;
}

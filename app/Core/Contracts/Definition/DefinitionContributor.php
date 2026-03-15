<?php

declare(strict_types=1);

namespace App\Core\Contracts\Definition;

interface DefinitionContributor
{
    public function contribute(DefinitionContext $context, DefinitionConsumer $consumer): void;
}

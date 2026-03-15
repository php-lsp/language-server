<?php

declare(strict_types=1);

namespace App\Core\Contracts\TypeDefinition;

interface TypeDefinitionContributor
{
    public function contribute(TypeDefinitionContext $context, TypeDefinitionConsumer $consumer): void;
}

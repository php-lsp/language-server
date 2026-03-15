<?php

declare(strict_types=1);

namespace App\Core\Contracts\Declaration;

interface DeclarationContributor
{
    public function contribute(DeclarationContext $context, DeclarationConsumer $consumer): void;
}

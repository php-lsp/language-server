<?php

namespace App\Core\Contracts\References;

interface ReferenceContributor
{
    public function contribute(ReferenceContext $context, ReferenceConsumer $consumer): void;
}

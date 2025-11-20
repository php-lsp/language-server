<?php
declare(strict_types=1);

namespace App\Core\Contracts\References;

interface ReferenceContributor
{
    public function contribute(ReferenceContext $context, ReferenceConsumer $consumer): void;
}

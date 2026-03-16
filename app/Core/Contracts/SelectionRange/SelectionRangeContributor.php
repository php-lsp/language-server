<?php

declare(strict_types=1);

namespace App\Core\Contracts\SelectionRange;

interface SelectionRangeContributor
{
    public function contribute(SelectionRangeContext $context, SelectionRangeConsumer $consumer): void;
}

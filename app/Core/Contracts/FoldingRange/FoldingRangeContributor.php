<?php

declare(strict_types=1);

namespace App\Core\Contracts\FoldingRange;

interface FoldingRangeContributor
{
    public function contribute(FoldingRangeContext $context, FoldingRangeConsumer $consumer): void;
}

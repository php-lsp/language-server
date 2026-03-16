<?php

declare(strict_types=1);

namespace App\Core\Contracts\FoldingRange;

#[\Attribute(\Attribute::TARGET_CLASS)]
final class AsFoldingRangeContributor
{
    public function __construct() {}
}

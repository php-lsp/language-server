<?php

declare(strict_types=1);

namespace App\Core\Contracts\InlayHint;

#[\Attribute(\Attribute::TARGET_CLASS)]
final class AsInlayHintContributor
{
    public function __construct() {}
}

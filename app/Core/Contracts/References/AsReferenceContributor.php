<?php

declare(strict_types=1);

namespace App\Core\Contracts\References;

#[\Attribute(\Attribute::TARGET_CLASS)]
final class AsReferenceContributor
{
    public function __construct() {}
}

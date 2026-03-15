<?php

declare(strict_types=1);

namespace App\Core\Contracts\Signature;

#[\Attribute(\Attribute::TARGET_CLASS)]
final class AsSignatureContributor
{
    public function __construct() {}
}

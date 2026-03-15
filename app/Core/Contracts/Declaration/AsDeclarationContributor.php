<?php

declare(strict_types=1);

namespace App\Core\Contracts\Declaration;

#[\Attribute(\Attribute::TARGET_CLASS)]
final class AsDeclarationContributor
{
    public function __construct() {}
}

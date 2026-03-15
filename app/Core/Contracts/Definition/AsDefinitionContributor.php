<?php

declare(strict_types=1);

namespace App\Core\Contracts\Definition;

#[\Attribute(\Attribute::TARGET_CLASS)]
final class AsDefinitionContributor
{
    public function __construct() {}
}

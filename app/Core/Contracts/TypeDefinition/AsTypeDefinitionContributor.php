<?php

declare(strict_types=1);

namespace App\Core\Contracts\TypeDefinition;

#[\Attribute(\Attribute::TARGET_CLASS)]
final class AsTypeDefinitionContributor
{
    public function __construct() {}
}

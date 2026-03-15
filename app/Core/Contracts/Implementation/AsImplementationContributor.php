<?php

declare(strict_types=1);

namespace App\Core\Contracts\Implementation;

#[\Attribute(\Attribute::TARGET_CLASS)]
final class AsImplementationContributor
{
    public function __construct() {}
}

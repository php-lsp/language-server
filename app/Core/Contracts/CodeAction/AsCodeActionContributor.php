<?php

declare(strict_types=1);

namespace App\Core\Contracts\CodeAction;

#[\Attribute(\Attribute::TARGET_CLASS)]
final class AsCodeActionContributor
{
    public function __construct() {}
}

<?php

declare(strict_types=1);

namespace App\Core\Contracts\Completion;

#[\Attribute(\Attribute::TARGET_CLASS)]
final class AsCompletionContributor
{
    public function __construct() {}
}

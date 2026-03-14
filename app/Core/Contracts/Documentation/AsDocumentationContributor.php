<?php

declare(strict_types=1);

namespace App\Core\Contracts\Documentation;

#[\Attribute(\Attribute::TARGET_CLASS)]
final class AsDocumentationContributor
{
    public function __construct() {}
}

<?php

declare(strict_types=1);

namespace App\Core\Contracts\Highlight;

#[\Attribute(\Attribute::TARGET_CLASS)]
final class AsDocumentHighlightContributor
{
    public function __construct() {}
}

<?php

declare(strict_types=1);

namespace App\Core\Contracts\SemanticToken;

#[\Attribute(\Attribute::TARGET_CLASS)]
final class AsSemanticTokenContributor
{
    public function __construct() {}
}

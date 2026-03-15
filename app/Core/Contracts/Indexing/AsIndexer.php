<?php

declare(strict_types=1);

namespace App\Core\Contracts\Indexing;

#[\Attribute(\Attribute::TARGET_CLASS)]
final class AsIndexer
{
    public function __construct() {}
}

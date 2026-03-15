<?php

declare(strict_types=1);

namespace App\Core\Contracts\PrefixMatcher;

interface PrefixMatcher
{
    public function match(string $textToMatch): bool;
}

<?php

declare(strict_types=1);

namespace App\Core\Contracts\PrefixMatcher;

use Override;

class StrContainsMatcher implements PrefixMatcher
{
    public function __construct(
        private readonly string $prefix,
    ) {}

    #[Override]
    public function match(string $textToMatch): bool
    {
        return str_contains($textToMatch, $this->prefix);
    }

    public static function matches(string $textToMatch, string $prefix): bool
    {
        return new self($prefix)->match($textToMatch);
    }
}

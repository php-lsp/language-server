<?php
declare(strict_types=1);

namespace App\Core\Contracts\PrefixMatcher;

class StrContainsMatcher implements PrefixMatcher
{
    public function __construct(
        private readonly string $prefix,
    )
    {
    }

    public function match(string $textToMatch): bool
    {
        return str_contains($textToMatch, $this->prefix);
    }

    public static function matches(string $textToMatch, string $prefix): bool
    {
        return new self($prefix)->match($textToMatch);
    }
}

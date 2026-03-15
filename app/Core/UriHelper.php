<?php

declare(strict_types=1);

namespace App\Core;

final class UriHelper
{
    public static function toFilePath(string $uri): ?string
    {
        if (str_starts_with($uri, 'file://')) {
            return substr($uri, offset: 7);
        }

        return null;
    }
}

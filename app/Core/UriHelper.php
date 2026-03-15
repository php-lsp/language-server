<?php

declare(strict_types=1);

namespace App\Core;

final class UriHelper
{
    public static function toFilePath(string $uri): ?string
    {
        if (!str_starts_with($uri, 'file://')) {
            return null;
        }

        $path = substr($uri, offset: 7);
        $path = urldecode($path);

        // Windows: file:///C:/path → C:/path
        if (preg_match('#^/[A-Za-z]:/#', $path)) {
            $path = substr($path, offset: 1);
        }

        return $path;
    }
}

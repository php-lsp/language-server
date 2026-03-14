<?php

declare(strict_types=1);

namespace App\Module\TypeSystem;

final class UriHelper
{
    public static function toFilePath(string $uri): string
    {
        if (!str_starts_with($uri, 'file://')) {
            return $uri;
        }

        $path = substr($uri, 7);
        $path = urldecode($path);

        // Windows: file:///C:/path → C:/path
        if (preg_match('#^/[A-Za-z]:/#', $path)) {
            $path = substr($path, 1);
        }

        return $path;
    }
}

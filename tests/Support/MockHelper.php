<?php

declare(strict_types=1);

namespace App\Tests\Support;

use PHPUnit\Framework\MockObject\Generator\Generator;
use PHPUnit\Framework\MockObject\MockObject;

final class MockHelper
{
    private static ?Generator $generator = null;

    private static function generator(): Generator
    {
        return self::$generator ??= new Generator();
    }

    public static function mock(string $class, bool $callConstructor = false): MockObject
    {
        return self::generator()->testDouble(
            $class,
            mockObject: true,
            markAsMockObject: true,
            callOriginalConstructor: $callConstructor,
        );
    }
}

<?php

declare(strict_types=1);

namespace App\Tests\Unit\Hydrator;

use App\Hydrator\StringLiteralType;
use App\Tests\Support\MockHelper;
use App\Tests\TestCase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\TestDox;
use TypeLang\Mapper\Exception\Mapping\InvalidValueException;
use TypeLang\Mapper\Runtime\Context;

#[Group('unit')]
final class StringLiteralTypeTest extends TestCase
{
    private function createContext(): Context
    {
        return MockHelper::mock(Context::class);
    }

    #[TestDox('match returns true when value matches the literal')]
    public function testMatchReturnsTrueWhenValueMatches(): void
    {
        $type = new StringLiteralType('hello');
        $context = $this->createContext();

        $this->assertTrue($type->match('hello', $context));
    }

    #[TestDox('match returns false when value does not match the literal')]
    public function testMatchReturnsFalseWhenValueDoesNotMatch(): void
    {
        $type = new StringLiteralType('hello');
        $context = $this->createContext();

        $this->assertFalse($type->match('world', $context));
        $this->assertFalse($type->match('', $context));
        $this->assertFalse($type->match(123, $context));
        $this->assertFalse($type->match(null, $context));
        $this->assertFalse($type->match(true, $context));
    }

    #[TestDox('cast returns the string when given a string')]
    public function testCastReturnsStringWhenGivenString(): void
    {
        $type = new StringLiteralType('hello');
        $context = $this->createContext();

        $this->assertSame('hello', $type->cast('hello', $context));
        $this->assertSame('other', $type->cast('other', $context));
    }

    #[TestDox('cast throws InvalidValueException when given non-string')]
    public function testCastThrowsExceptionForNonString(): void
    {
        $type = new StringLiteralType('hello');
        $context = $this->createContext();

        $this->expectException(InvalidValueException::class);

        $type->cast(123, $context);
    }
}

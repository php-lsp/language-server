<?php

declare(strict_types=1);

namespace App\Tests\Unit\Core\Contracts\PrefixMatcher;

use App\Core\Contracts\PrefixMatcher\StrContainsMatcher;
use App\Tests\TestCase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\TestDox;

#[Group('unit')]
final class StrContainsMatcherTest extends TestCase
{
    #[TestDox('match returns true when text contains prefix')]
    public function testMatchReturnsTrue(): void
    {
        $matcher = new StrContainsMatcher('foo');
        $this->assertTrue($matcher->match('foobar'));
        $this->assertTrue($matcher->match('xfoox'));
    }

    #[TestDox('match returns false when text does not contain prefix')]
    public function testMatchReturnsFalse(): void
    {
        $matcher = new StrContainsMatcher('foo');
        $this->assertFalse($matcher->match('bar'));
        $this->assertFalse($matcher->match(''));
    }

    #[TestDox('static matches method works')]
    public function testStaticMatches(): void
    {
        $this->assertTrue(StrContainsMatcher::matches('hello world', 'world'));
        $this->assertFalse(StrContainsMatcher::matches('hello', 'world'));
    }
}

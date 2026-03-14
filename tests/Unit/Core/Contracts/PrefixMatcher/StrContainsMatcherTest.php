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
    #[TestDox('matches when prefix is contained')]
    public function testMatchesContained(): void
    {
        $matcher = new StrContainsMatcher('foo');
        $this->assertTrue($matcher->match('foobar'));
    }

    #[TestDox('does not match when prefix is absent')]
    public function testDoesNotMatch(): void
    {
        $matcher = new StrContainsMatcher('baz');
        $this->assertFalse($matcher->match('foobar'));
    }

    #[TestDox('matches empty prefix against anything')]
    public function testEmptyPrefix(): void
    {
        $matcher = new StrContainsMatcher('');
        $this->assertTrue($matcher->match('anything'));
    }

    #[TestDox('static matches helper works')]
    public function testStaticMatches(): void
    {
        $this->assertTrue(StrContainsMatcher::matches('hello world', 'world'));
        $this->assertFalse(StrContainsMatcher::matches('hello', 'xyz'));
    }
}

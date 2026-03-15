<?php

declare(strict_types=1);

namespace App\Tests\Unit\Core;

use App\Core\UriHelper;
use App\Tests\TestCase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\TestDox;

#[Group('unit')]
final class UriHelperTest extends TestCase
{
    #[TestDox('converts file:// URI to file path')]
    public function testToFilePathWithFileScheme(): void
    {
        $result = UriHelper::toFilePath('file:///home/user/test.php');

        $this->assertSame('/home/user/test.php', $result);
    }

    #[TestDox('returns null for non-file URIs')]
    public function testToFilePathWithHttpScheme(): void
    {
        $result = UriHelper::toFilePath('http://example.com/test.php');

        $this->assertNull($result);
    }

    #[TestDox('returns null for empty string')]
    public function testToFilePathWithEmptyString(): void
    {
        $result = UriHelper::toFilePath('');

        $this->assertNull($result);
    }

    #[TestDox('handles file URI with spaces')]
    public function testToFilePathWithSpaces(): void
    {
        $result = UriHelper::toFilePath('file:///home/user/my project/test.php');

        $this->assertSame('/home/user/my project/test.php', $result);
    }
}

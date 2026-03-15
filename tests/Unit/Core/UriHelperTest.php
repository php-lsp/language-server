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

    #[TestDox('decodes percent-encoded characters')]
    public function testToFilePathDecodesPercentEncoding(): void
    {
        $result = UriHelper::toFilePath('file:///path%20with%20spaces/test.php');

        $this->assertSame('/path with spaces/test.php', $result);
    }

    #[TestDox('handles Windows drive letter')]
    public function testToFilePathHandlesWindowsDriveLetter(): void
    {
        $result = UriHelper::toFilePath('file:///C:/Users/dev/test.php');

        $this->assertSame('C:/Users/dev/test.php', $result);
    }

    #[TestDox('converts Unix path to file URI')]
    public function testToFileUriUnixPath(): void
    {
        $result = UriHelper::toFileUri('/home/user/test.php');

        $this->assertSame('file:///home/user/test.php', $result);
    }

    #[TestDox('converts Windows path to file URI')]
    public function testToFileUriWindowsPath(): void
    {
        $result = UriHelper::toFileUri('C:/Users/dev/test.php');

        $this->assertSame('file:///C:/Users/dev/test.php', $result);
    }

    #[TestDox('converts Windows backslash path to file URI')]
    public function testToFileUriWindowsBackslashPath(): void
    {
        $result = UriHelper::toFileUri('C:\\Users\\dev\\test.php');

        $this->assertSame('file:///C:/Users/dev/test.php', $result);
    }

    #[TestDox('toFileUri and toFilePath are inverse operations for Unix paths')]
    public function testRoundTripUnix(): void
    {
        $path = '/home/user/project/src/test.php';
        $uri = UriHelper::toFileUri($path);

        $this->assertSame($path, UriHelper::toFilePath($uri));
    }

    #[TestDox('toFileUri and toFilePath are inverse operations for Windows paths')]
    public function testRoundTripWindows(): void
    {
        $path = 'C:/Users/dev/project/test.php';
        $uri = UriHelper::toFileUri($path);

        $this->assertSame($path, UriHelper::toFilePath($uri));
    }
}

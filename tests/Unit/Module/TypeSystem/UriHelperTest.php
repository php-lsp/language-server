<?php

declare(strict_types=1);

namespace App\Tests\Unit\Module\TypeSystem;

use App\Core\UriHelper;
use App\Tests\TestCase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\TestDox;

#[Group('unit')]
final class UriHelperTest extends TestCase
{
    #[TestDox('converts file URI to path')]
    public function testConvertsFileUri(): void
    {
        $this->assertSame('/home/user/file.php', UriHelper::toFilePath('file:///home/user/file.php'));
    }

    #[TestDox('decodes percent-encoded characters')]
    public function testDecodesPercentEncoding(): void
    {
        $this->assertSame('/path with spaces/file.php', UriHelper::toFilePath('file:///path%20with%20spaces/file.php'));
    }

    #[TestDox('handles Windows drive letter')]
    public function testHandlesWindowsDriveLetter(): void
    {
        $this->assertSame('C:/Users/dev/file.php', UriHelper::toFilePath('file:///C:/Users/dev/file.php'));
    }

    #[TestDox('returns null for non-file URI')]
    public function testReturnsNullForNonFileUri(): void
    {
        $this->assertNull(UriHelper::toFilePath('untitled:Untitled-1'));
    }

    #[TestDox('returns null for plain path without scheme')]
    public function testReturnsNullForPlainPath(): void
    {
        $this->assertNull(UriHelper::toFilePath('/tmp/test.php'));
    }
}

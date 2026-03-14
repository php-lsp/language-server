<?php

declare(strict_types=1);

namespace App\Tests\Unit\Module\PsiFile;

use App\Module\PsiFile\SourceFileRoot;
use App\Tests\Support\PsiFileFactory;
use App\Tests\TestCase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\TestDox;

#[Group('unit')]
final class SourceFileRootTest extends TestCase
{
    #[TestDox('exposes children, document, and errors')]
    public function testProperties(): void
    {
        $document = PsiFileFactory::document('<?php echo 1;');
        $root = new SourceFileRoot([], $document, []);

        $this->assertSame([], $root->children);
        $this->assertSame($document, $root->document);
        $this->assertSame([], $root->errors);
    }
}

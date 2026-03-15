<?php

declare(strict_types=1);

namespace App\Tests\Unit\Core\PsiFile;

use App\Core\Contracts\PsiFile\PsiFileInterface;
use App\Core\Contracts\PsiFile\PsiFileManagerInterface;
use App\Module\PsiFile\InMemoryPsiFileManager;
use App\Module\PsiFile\PHPPsiFile;
use App\Tests\Support\PsiFileFactory;
use App\Tests\TestCase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\TestDox;

#[Group('unit')]
final class PsiFileInterfaceTest extends TestCase
{
    #[TestDox('PHPPsiFile implements PsiFileInterface')]
    public function testPhpPsiFileImplementsInterface(): void
    {
        $psiFile = PsiFileFactory::fromCode('<?php echo 1;');
        $this->assertInstanceOf(PsiFileInterface::class, $psiFile);
    }

    #[TestDox('InMemoryPsiFileManager implements PsiFileManagerInterface')]
    public function testInMemoryPsiFileManagerImplementsInterface(): void
    {
        $this->assertTrue(
            is_subclass_of(InMemoryPsiFileManager::class, PsiFileManagerInterface::class),
        );
    }
}

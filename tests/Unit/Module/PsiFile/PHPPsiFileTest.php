<?php

declare(strict_types=1);

namespace App\Tests\Unit\Module\PsiFile;

use App\Module\PsiFile\PHPPsiFile;
use App\Tests\Support\PsiFileFactory;
use App\Tests\TestCase;
use Lsp\Protocol\Type\Position;
use PhpParser\Node;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\TestDox;

#[Group('unit')]
final class PHPPsiFileTest extends TestCase
{
    #[TestDox('findAtPosition with int offset finds nodes')]
    public function testFindAtPositionInt(): void
    {
        $file = PsiFileFactory::fromCode('<?php class Foo {}');

        $nodes = $file->findAtPosition(8);

        $this->assertNotEmpty($nodes);
        $this->assertContainsOnlyInstancesOf(Node::class, $nodes);
    }

    #[TestDox('findAtPosition with Position finds nodes')]
    public function testFindAtPositionObject(): void
    {
        $file = PsiFileFactory::fromCode('<?php class Foo {}');
        $position = new Position(0, 10);

        $nodes = $file->findAtPosition($position);

        $this->assertNotEmpty($nodes);
    }

    #[TestDox('findAtPosition returns empty for out-of-range position')]
    public function testFindAtPositionEmpty(): void
    {
        $file = PsiFileFactory::fromCode('<?php echo 1;');
        $position = new Position(10, 0);

        $nodes = $file->findAtPosition($position);

        $this->assertEmpty($nodes);
    }

    #[TestDox('findLastAtPosition returns last matching node')]
    public function testFindLastAtPosition(): void
    {
        $file = PsiFileFactory::fromCode('<?php class Foo {}');
        $position = new Position(0, 10);

        $node = $file->findLastAtPosition($position);

        $this->assertInstanceOf(Node::class, $node);
    }

    #[TestDox('findLastAtPosition returns null when no match')]
    public function testFindLastAtPositionNull(): void
    {
        $file = PsiFileFactory::fromCode('<?php echo 1;');
        $position = new Position(10, 0);

        $node = $file->findLastAtPosition($position);

        $this->assertNull($node);
    }
}

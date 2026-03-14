<?php

declare(strict_types=1);

namespace App\Tests\Unit\Module\PsiFile;

use App\Tests\Support\ProtocolFactory;
use App\Tests\Support\PsiFileFactory;
use App\Tests\TestCase;
use PhpParser\Node;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\TestDox;

#[Group('unit')]
final class PHPPsiFileTest extends TestCase
{
    #[TestDox('findAtPosition returns nodes at given position')]
    public function testFindAtPosition(): void
    {
        $psiFile = PsiFileFactory::fromCode('<?php function foo() {}');

        $nodes = $psiFile->findAtPosition(ProtocolFactory::position(0, 16));

        $this->assertNotEmpty($nodes);
    }

    #[TestDox('findAtPosition with int offset')]
    public function testFindAtPositionInt(): void
    {
        $psiFile = PsiFileFactory::fromCode('<?php function foo() {}');

        $nodes = $psiFile->findAtPosition(10);

        $this->assertNotEmpty($nodes);
    }

    #[TestDox('findLastAtPosition returns last matching node')]
    public function testFindLastAtPosition(): void
    {
        $psiFile = PsiFileFactory::fromCode('<?php function foo() {}');

        $node = $psiFile->findLastAtPosition(ProtocolFactory::position(0, 16));

        $this->assertInstanceOf(Node::class, $node);
    }

    #[TestDox('findLastAtPosition returns null for empty position')]
    public function testFindLastAtPositionReturnsNull(): void
    {
        $psiFile = PsiFileFactory::fromCode('<?php ');

        $node = $psiFile->findLastAtPosition(ProtocolFactory::position(10, 0));

        $this->assertNull($node);
    }
}

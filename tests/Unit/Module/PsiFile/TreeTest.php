<?php

declare(strict_types=1);

namespace App\Tests\Unit\Module\PsiFile;

use App\Module\PsiFile\Tree;
use App\Tests\Support\ProtocolFactory;
use App\Tests\Support\PsiFileFactory;
use App\Tests\TestCase;
use PhpParser\Node;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\TestDox;

#[Group('unit')]
final class TreeTest extends TestCase
{
    #[TestDox('childrenOfType finds classes')]
    public function testChildrenOfTypeFindsClasses(): void
    {
        $psiFile = PsiFileFactory::fromCode('<?php class Foo {} class Bar {}');

        $classes = Tree::childrenOfType($psiFile->ast, Node\Stmt\Class_::class);

        $this->assertCount(2, $classes);
    }

    #[TestDox('childrenOfType returns empty for null')]
    public function testChildrenOfTypeNull(): void
    {
        $this->assertSame([], Tree::childrenOfType(null, Node\Stmt\Class_::class));
    }

    #[TestDox('childrenOfTypes finds multiple types')]
    public function testChildrenOfTypes(): void
    {
        $psiFile = PsiFileFactory::fromCode('<?php class Foo {} function bar() {}');

        $nodes = Tree::childrenOfTypes($psiFile->ast, Node\Stmt\Class_::class, Node\Stmt\Function_::class);

        $this->assertCount(2, $nodes);
    }

    #[TestDox('parentOfType traverses up')]
    public function testParentOfType(): void
    {
        $psiFile = PsiFileFactory::fromCode('<?php class Foo { public function bar() {} }');

        $methods = Tree::childrenOfType($psiFile->ast, Node\Stmt\ClassMethod::class);
        $this->assertNotEmpty($methods);

        $parent = Tree::parentOfType($methods[0], Node\Stmt\Class_::class);
        $this->assertInstanceOf(Node\Stmt\Class_::class, $parent);
    }

    #[TestDox('parentOfType returns null when not found')]
    public function testParentOfTypeNotFound(): void
    {
        $psiFile = PsiFileFactory::fromCode('<?php function foo() {}');

        $functions = Tree::childrenOfType($psiFile->ast, Node\Stmt\Function_::class);
        $parent = Tree::parentOfType($functions[0], Node\Stmt\Class_::class);

        $this->assertNull($parent);
    }

    #[TestDox('parent returns direct parent node')]
    public function testParent(): void
    {
        $psiFile = PsiFileFactory::fromCode('<?php class Foo { public function bar() {} }');

        $methods = Tree::childrenOfType($psiFile->ast, Node\Stmt\ClassMethod::class);
        $parent = Tree::parent($methods[0]);

        $this->assertInstanceOf(Node::class, $parent);
    }

    #[TestDox('getRange returns range for node')]
    public function testGetRange(): void
    {
        $psiFile = PsiFileFactory::fromCode('<?php class Foo {}');

        $classes = Tree::childrenOfType($psiFile->ast, Node\Stmt\Class_::class);
        $range = Tree::getRange($classes[0], $psiFile);

        $this->assertSame(0, $range->start->line);
    }

    #[TestDox('toColumn computes column from position')]
    public function testToColumn(): void
    {
        $document = PsiFileFactory::document("<?php\necho 1;");

        $col = Tree::toColumn($document, 6);
        $this->assertIsInt($col);
    }

    #[TestDox('toLineColumn returns line and column')]
    public function testToLineColumn(): void
    {
        $document = PsiFileFactory::document("<?php\necho 1;");

        [$line, $col] = Tree::toLineColumn($document, 6);

        $this->assertSame(1, $line);
        $this->assertIsInt($col);
    }

    #[TestDox('toString returns empty for null')]
    public function testToStringNull(): void
    {
        $this->assertSame('', Tree::toString(null));
    }

    #[TestDox('toString returns string for stringable node')]
    public function testToStringStringable(): void
    {
        $psiFile = PsiFileFactory::fromCode('<?php class Foo {}');
        $classes = Tree::childrenOfType($psiFile->ast, Node\Stmt\Class_::class);
        $result = Tree::toString($classes[0]->name);
        $this->assertSame('Foo', $result);
    }

    #[TestDox('toString returns var_export for non-stringable node')]
    public function testToStringNonStringable(): void
    {
        // Create a minimal node without parent links to avoid circular references
        $node = new Node\Scalar\Int_(42);
        $result = Tree::toString($node);
        $this->assertStringContainsString('-----', $result);
    }

    #[TestDox('childrenOfType traverses into namespaces')]
    public function testChildrenOfTypeInNamespace(): void
    {
        $psiFile = PsiFileFactory::fromCode('<?php namespace App; class Foo {}');
        $classes = Tree::childrenOfType($psiFile->ast, Node\Stmt\Class_::class);
        $this->assertCount(1, $classes);
    }

    #[TestDox('childrenOfTypes returns empty for null')]
    public function testChildrenOfTypesNull(): void
    {
        $this->assertSame([], Tree::childrenOfTypes(null, Node\Stmt\Class_::class));
    }

    #[TestDox('getNodeChildren handles function stmts')]
    public function testGetNodeChildrenFunction(): void
    {
        $psiFile = PsiFileFactory::fromCode('<?php function foo() { class Bar {} }');
        $classes = Tree::childrenOfType($psiFile->ast, Node\Stmt\Class_::class);
        $this->assertCount(1, $classes);
    }

    #[TestDox('getParentNodes yields parent chain')]
    public function testGetParentNodes(): void
    {
        $psiFile = PsiFileFactory::fromCode('<?php class Foo { public function bar() {} }');

        $methods = Tree::childrenOfType($psiFile->ast, Node\Stmt\ClassMethod::class);
        $parents = iterator_to_array(Tree::getParentNodes($methods[0]));

        $this->assertNotEmpty($parents);
    }

    #[TestDox('getParentNodesIncluding yields nodes')]
    public function testGetParentNodesIncluding(): void
    {
        $psiFile = PsiFileFactory::fromCode('<?php class Foo { public function bar() {} }');

        $nodes = $psiFile->findAtPosition(ProtocolFactory::position(0, 40));
        $lastNode = end($nodes);

        $chain = iterator_to_array(Tree::getParentNodesIncluding($lastNode));

        $this->assertNotEmpty($chain);
    }
}

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

    #[TestDox('toString returns class name for non-stringable node')]
    public function testToStringNonStringable(): void
    {
        $node = new Node\Scalar\Int_(42);
        $result = Tree::toString($node);
        $this->assertNotEmpty($result);
    }

    #[TestDox('toString does not crash on nodes with circular references')]
    public function testToStringCircularReference(): void
    {
        $psiFile = PsiFileFactory::fromCode('<?php echo 1;');

        // findLastAtPosition returns a node with parent attributes set,
        // creating circular references that var_export cannot handle.
        $element = $psiFile->findLastAtPosition(ProtocolFactory::position(0, 8));
        $this->assertNotNull($element);

        // This must not throw ErrorException about circular references.
        $result = Tree::toString($element);
        $this->assertIsString($result);
        $this->assertNotEmpty($result);
    }

    #[TestDox('childrenOfType traverses into namespaces')]
    public function testChildrenOfTypeInNamespace(): void
    {
        $psiFile = PsiFileFactory::fromCode('<?php namespace App; class Foo {}');
        $classes = Tree::childrenOfType($psiFile->ast, Node\Stmt\Class_::class);
        $this->assertCount(1, $classes);
    }

    #[TestDox('toLspLine converts 1-based to 0-based')]
    public function testToLspLine(): void
    {
        $this->assertSame(0, Tree::toLspLine(1));
        $this->assertSame(4, Tree::toLspLine(5));
        $this->assertSame(99, Tree::toLspLine(100));
    }

    #[TestDox('toParserLine converts 0-based to 1-based')]
    public function testToParserLine(): void
    {
        $this->assertSame(1, Tree::toParserLine(0));
        $this->assertSame(5, Tree::toParserLine(4));
        $this->assertSame(100, Tree::toParserLine(99));
    }

    #[TestDox('toLspLine and toParserLine are inverse operations')]
    public function testLspParserLineRoundTrip(): void
    {
        for ($i = 1; $i <= 10; $i++) {
            $this->assertSame($i, Tree::toParserLine(Tree::toLspLine($i)));
        }
    }

    #[TestDox('nodeStartLine returns 0-based line for node')]
    public function testNodeStartLine(): void
    {
        $psiFile = PsiFileFactory::fromCode("<?php\nclass Foo {}");

        $classes = Tree::childrenOfType($psiFile->ast, Node\Stmt\Class_::class);
        $startLine = Tree::nodeStartLine($classes[0]);

        $this->assertSame(1, $startLine);
    }

    #[TestDox('nodeEndLine returns 0-based line for node')]
    public function testNodeEndLine(): void
    {
        $psiFile = PsiFileFactory::fromCode("<?php\nclass Foo {\n}");

        $classes = Tree::childrenOfType($psiFile->ast, Node\Stmt\Class_::class);
        $endLine = Tree::nodeEndLine($classes[0]);

        $this->assertSame(2, $endLine);
    }

    #[TestDox('errorRange converts php-parser Error to LSP Range')]
    public function testErrorRange(): void
    {
        $document = PsiFileFactory::document("<?php\nclass { }");
        $root = PsiFileFactory::getParser()->parse($document);

        $this->assertNotEmpty($root->errors);

        $range = Tree::errorRange($root->errors[0], $document);

        $this->assertGreaterThanOrEqual(0, $range->start->line);
        $this->assertGreaterThanOrEqual(0, $range->start->character);
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

<?php

declare(strict_types=1);

namespace App\Tests\Unit\Module\CallHierarchy;

use App\Module\CallHierarchy\CallHierarchyHelper;
use App\Module\Indexing\Data\FunctionData;
use App\Module\Indexing\Data\MethodData;
use App\Module\Indexing\Data\Visibility;
use App\Tests\Support\PsiFileFactory;
use App\Tests\TestCase;
use Lsp\Protocol\Type\SymbolKind;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\TestDox;

#[Group('unit')]
final class CallHierarchyHelperTest extends TestCase
{
    #[TestDox('findEnclosingCallable returns function info')]
    public function testFindEnclosingCallableReturnsFunction(): void
    {
        $code = '<?php function foo() { $x = 1; }';
        $file = PsiFileFactory::fromCode($code);

        $pos = strpos($code, '$x');
        $result = CallHierarchyHelper::findEnclosingCallable($file, $pos);

        $this->assertNotNull($result);
        $this->assertSame('foo', $result['name']);
        $this->assertSame(SymbolKind::FunctionKind, $result['kind']);
        $this->assertSame('function', $result['data']['type']);
    }

    #[TestDox('findEnclosingCallable returns method info')]
    public function testFindEnclosingCallableReturnsMethod(): void
    {
        $code = '<?php class Bar { public function baz() { $x = 1; } }';
        $file = PsiFileFactory::fromCode($code);

        $pos = strpos($code, '$x');
        $result = CallHierarchyHelper::findEnclosingCallable($file, $pos);

        $this->assertNotNull($result);
        $this->assertSame('Bar::baz', $result['name']);
        $this->assertSame(SymbolKind::MethodKind, $result['kind']);
        $this->assertSame('method', $result['data']['type']);
        $this->assertSame('baz', $result['data']['name']);
        $this->assertSame('Bar', $result['data']['class']);
    }

    #[TestDox('findEnclosingCallable returns null in global scope')]
    public function testFindEnclosingCallableReturnsNullInGlobalScope(): void
    {
        $code = '<?php $x = 1;';
        $file = PsiFileFactory::fromCode($code);

        $pos = strpos($code, '$x');
        $result = CallHierarchyHelper::findEnclosingCallable($file, $pos);

        $this->assertNull($result);
    }

    #[TestDox('makeNameRange creates non-zero-width range')]
    public function testMakeNameRangeCreatesNonZeroWidthRange(): void
    {
        $code = '<?php function myFunc() {}';
        $document = PsiFileFactory::document($code);

        $pos = strpos($code, 'myFunc');
        $range = CallHierarchyHelper::makeNameRange($document, $pos, strlen('myFunc'));

        $this->assertSame($range->start->line, $range->end->line);
        $this->assertNotSame($range->start->character, $range->end->character);
        $this->assertSame(6, $range->end->character - $range->start->character);
    }

    #[TestDox('findFunctionNameRange returns name range')]
    public function testFindFunctionNameRangeReturnsNameRange(): void
    {
        $code = '<?php function myFunc() {}';
        $file = PsiFileFactory::fromCode($code);

        $funcData = new FunctionData(
            fqn: 'myFunc',
            startPosition: strpos($code, 'function'),
            endPosition: strlen($code) - 1,
            returnType: null,
            parameters: [],
        );

        $range = CallHierarchyHelper::findFunctionNameRange($file, $funcData);

        $this->assertNotNull($range);
        $this->assertNotSame($range->start->character, $range->end->character);
    }

    #[TestDox('findMethodNameRange returns name range')]
    public function testFindMethodNameRangeReturnsNameRange(): void
    {
        $code = '<?php class Foo { public function bar() {} }';
        $file = PsiFileFactory::fromCode($code);

        $methodData = new MethodData(
            name: 'bar',
            className: 'Foo',
            startPosition: strpos($code, 'public'),
            endPosition: strpos($code, '{}') + 1,
            visibility: Visibility::Public,
            isStatic: false,
            isAbstract: false,
            returnType: null,
            parameters: [],
        );

        $range = CallHierarchyHelper::findMethodNameRange($file, $methodData);

        $this->assertNotNull($range);
        $this->assertNotSame($range->start->character, $range->end->character);
    }

    #[TestDox('findEnclosingCallable returns non-zero-width selectionRange')]
    public function testFindEnclosingCallableSelectionRangeIsNonZeroWidth(): void
    {
        $code = '<?php function foo() { $x = 1; }';
        $file = PsiFileFactory::fromCode($code);

        $pos = strpos($code, '$x');
        $result = CallHierarchyHelper::findEnclosingCallable($file, $pos);

        $this->assertNotNull($result);
        $selectionRange = $result['selectionRange'];
        $this->assertNotSame(
            $selectionRange->start->character,
            $selectionRange->end->character,
            'selectionRange from findEnclosingCallable should not be zero-width',
        );
    }
}

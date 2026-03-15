<?php

declare(strict_types=1);

namespace App\Tests\Unit\Controller;

use App\Controller\WorkspaceSymbolController;
use App\Module\Indexing\Data\ClassData;
use App\Module\Indexing\Data\ConstantData;
use App\Module\Indexing\Data\EnumData;
use App\Module\Indexing\Data\FunctionData;
use App\Module\Indexing\Data\InterfaceData;
use App\Module\Indexing\Data\MethodData;
use App\Module\Indexing\Data\PropertyData;
use App\Module\Indexing\Data\TraitData;
use App\Module\Indexing\Data\Visibility;
use App\Tests\Support\IndexTestHelper;
use App\Tests\TestCase;
use Lsp\Protocol\Type\SymbolKind;
use Lsp\Protocol\Type\WorkspaceSymbolParams;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\TestDox;

#[Group('unit')]
final class WorkspaceSymbolControllerTest extends TestCase
{
    #[TestDox('returns empty when no index data')]
    public function testReturnsEmptyWhenNoData(): void
    {
        $lookup = IndexTestHelper::createLookup();
        $controller = new WorkspaceSymbolController($lookup);

        $result = $controller(new WorkspaceSymbolParams(query: 'Foo'));

        $this->assertEmpty($result);
    }

    #[TestDox('finds class symbols')]
    public function testFindsClassSymbols(): void
    {
        $lookup = IndexTestHelper::createLookup([
            'php.classes.fqn' => [
                'file:///test.php' => ['App\\Foo' => new ClassData('App\\Foo', 0, 10, false, false, false, null, [])],
            ],
        ]);
        $controller = new WorkspaceSymbolController($lookup);

        $result = $controller(new WorkspaceSymbolParams(query: 'Foo'));

        $this->assertCount(1, $result);
        $this->assertSame('App\\Foo', $result[0]->name);
        $this->assertSame(SymbolKind::ClassKind, $result[0]->kind);
    }

    #[TestDox('finds interface symbols')]
    public function testFindsInterfaceSymbols(): void
    {
        $lookup = IndexTestHelper::createLookup([
            'php.interfaces.fqn' => [
                'file:///test.php' => ['App\\Bar' => new InterfaceData('App\\Bar', 0, 10, [])],
            ],
        ]);
        $controller = new WorkspaceSymbolController($lookup);

        $result = $controller(new WorkspaceSymbolParams(query: 'Bar'));

        $this->assertCount(1, $result);
        $this->assertSame(SymbolKind::InterfaceKind, $result[0]->kind);
    }

    #[TestDox('finds function symbols')]
    public function testFindsFunctionSymbols(): void
    {
        $lookup = IndexTestHelper::createLookup([
            'php.functions.fqn' => [
                'file:///test.php' => ['myFunc' => new FunctionData('myFunc', 0, 20, null, [])],
            ],
        ]);
        $controller = new WorkspaceSymbolController($lookup);

        $result = $controller(new WorkspaceSymbolParams(query: 'myFunc'));

        $this->assertCount(1, $result);
        $this->assertSame(SymbolKind::FunctionKind, $result[0]->kind);
    }

    #[TestDox('finds method symbols')]
    public function testFindsMethodSymbols(): void
    {
        $lookup = IndexTestHelper::createLookup([
            'php.classMethods.fqn' => [
                'file:///test.php' => ['Foo::bar' => new MethodData('bar', 'Foo', 0, 10, Visibility::Public, false, false, null, [])],
            ],
        ]);
        $controller = new WorkspaceSymbolController($lookup);

        $result = $controller(new WorkspaceSymbolParams(query: 'bar'));

        $this->assertCount(1, $result);
        $this->assertSame(SymbolKind::MethodKind, $result[0]->kind);
        $this->assertSame('Foo', $result[0]->containerName);
    }

    #[TestDox('finds property symbols')]
    public function testFindsPropertySymbols(): void
    {
        $lookup = IndexTestHelper::createLookup([
            'php.properties.fqn' => [
                'file:///test.php' => ['Foo::$name' => new PropertyData('name', 'Foo', 0, 10, Visibility::Public, 'string', false, false, false)],
            ],
        ]);
        $controller = new WorkspaceSymbolController($lookup);

        $result = $controller(new WorkspaceSymbolParams(query: 'name'));

        $this->assertCount(1, $result);
        $this->assertSame('$name', $result[0]->name);
        $this->assertSame(SymbolKind::PropertyKind, $result[0]->kind);
    }

    #[TestDox('finds enum symbols')]
    public function testFindsEnumSymbols(): void
    {
        $lookup = IndexTestHelper::createLookup([
            'php.enums.fqn' => [
                'file:///test.php' => ['Suit' => new EnumData('Suit', 0, 10, 'string', [])],
            ],
        ]);
        $controller = new WorkspaceSymbolController($lookup);

        $result = $controller(new WorkspaceSymbolParams(query: 'Suit'));

        $this->assertCount(1, $result);
        $this->assertSame(SymbolKind::EnumKind, $result[0]->kind);
    }

    #[TestDox('finds trait symbols')]
    public function testFindsTraitSymbols(): void
    {
        $lookup = IndexTestHelper::createLookup([
            'php.traits.fqn' => [
                'file:///test.php' => ['MyTrait' => new TraitData('MyTrait', 0, 10)],
            ],
        ]);
        $controller = new WorkspaceSymbolController($lookup);

        $result = $controller(new WorkspaceSymbolParams(query: 'MyTrait'));

        $this->assertCount(1, $result);
        $this->assertSame('MyTrait', $result[0]->name);
    }

    #[TestDox('finds constant symbols')]
    public function testFindsConstantSymbols(): void
    {
        $lookup = IndexTestHelper::createLookup([
            'php.constants.fqn' => [
                'file:///test.php' => ['MY_CONST' => new ConstantData('MY_CONST', null, 0, 10, null, null)],
            ],
        ]);
        $controller = new WorkspaceSymbolController($lookup);

        $result = $controller(new WorkspaceSymbolParams(query: 'MY_CONST'));

        $this->assertCount(1, $result);
        $this->assertSame(SymbolKind::ConstantKind, $result[0]->kind);
    }

    #[TestDox('returns all symbols for empty query')]
    public function testReturnsAllForEmptyQuery(): void
    {
        $lookup = IndexTestHelper::createLookup([
            'php.classes.fqn' => [
                'file:///test.php' => ['Foo' => new ClassData('Foo', 0, 10, false, false, false, null, [])],
            ],
            'php.functions.fqn' => [
                'file:///test.php' => ['bar' => new FunctionData('bar', 0, 20, null, [])],
            ],
        ]);
        $controller = new WorkspaceSymbolController($lookup);

        $result = $controller(new WorkspaceSymbolParams(query: ''));

        $this->assertCount(2, $result);
    }

    #[TestDox('filters by query string')]
    public function testFiltersByQuery(): void
    {
        $lookup = IndexTestHelper::createLookup([
            'php.classes.fqn' => [
                'file:///test.php' => [
                    'Foo' => new ClassData('Foo', 0, 10, false, false, false, null, []),
                    'Bar' => new ClassData('Bar', 11, 20, false, false, false, null, []),
                ],
            ],
        ]);
        $controller = new WorkspaceSymbolController($lookup);

        $result = $controller(new WorkspaceSymbolParams(query: 'Foo'));

        $this->assertCount(1, $result);
        $this->assertSame('Foo', $result[0]->name);
    }
}

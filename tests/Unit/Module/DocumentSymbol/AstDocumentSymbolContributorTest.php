<?php

declare(strict_types=1);

namespace App\Tests\Unit\Module\DocumentSymbol;

use App\Core\Contracts\DocumentSymbol\DocumentSymbolConsumer;
use App\Core\Contracts\DocumentSymbol\DocumentSymbolContext;
use App\Core\Contracts\PsiFile\PsiFileManagerInterface;
use App\Module\DocumentSymbol\AstDocumentSymbolContributor;
use App\Tests\Support\MockHelper;
use App\Tests\Support\ProtocolFactory;
use App\Tests\Support\PsiFileFactory;
use App\Tests\TestCase;
use Lsp\Extension\DocumentManager\Editor\EditorInterface;
use Lsp\Protocol\Type\SymbolKind;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\TestDox;

#[Group('unit')]
final class AstDocumentSymbolContributorTest extends TestCase
{
    private function createContext(string $code): DocumentSymbolContext
    {
        $psiFile = PsiFileFactory::fromCode($code);
        $fileManager = MockHelper::mock(PsiFileManagerInterface::class);
        $fileManager->method('findPsiFile')->willReturn($psiFile);
        $editor = MockHelper::mock(EditorInterface::class);

        return new DocumentSymbolContext(
            ProtocolFactory::textDocumentIdentifier(),
            $editor,
            $fileManager,
        );
    }

    #[TestDox('produces class with methods and properties')]
    public function testClassWithMembers(): void
    {
        $context = $this->createContext('<?php class Foo { public $bar; public function baz() {} }');
        $consumer = new DocumentSymbolConsumer();

        (new AstDocumentSymbolContributor())->contribute($context, $consumer);

        $this->assertCount(1, $consumer->results);
        $this->assertSame('Foo', $consumer->results[0]->name);
        $this->assertSame(SymbolKind::ClassKind, $consumer->results[0]->kind);
        $this->assertCount(2, $consumer->results[0]->children);
    }

    #[TestDox('produces interface symbol')]
    public function testInterface(): void
    {
        $context = $this->createContext('<?php interface Baz { public function run(): void; }');
        $consumer = new DocumentSymbolConsumer();

        (new AstDocumentSymbolContributor())->contribute($context, $consumer);

        $this->assertCount(1, $consumer->results);
        $this->assertSame('Baz', $consumer->results[0]->name);
        $this->assertSame(SymbolKind::InterfaceKind, $consumer->results[0]->kind);
    }

    #[TestDox('produces trait symbol')]
    public function testTrait(): void
    {
        $context = $this->createContext('<?php trait MyTrait { public function doStuff() {} }');
        $consumer = new DocumentSymbolConsumer();

        (new AstDocumentSymbolContributor())->contribute($context, $consumer);

        $this->assertCount(1, $consumer->results);
        $this->assertSame('MyTrait', $consumer->results[0]->name);
        $this->assertSame('trait', $consumer->results[0]->detail);
    }

    #[TestDox('produces enum with cases')]
    public function testEnum(): void
    {
        $context = $this->createContext('<?php enum Color { case Red; case Blue; }');
        $consumer = new DocumentSymbolConsumer();

        (new AstDocumentSymbolContributor())->contribute($context, $consumer);

        $this->assertCount(1, $consumer->results);
        $this->assertSame('Color', $consumer->results[0]->name);
        $this->assertSame(SymbolKind::EnumKind, $consumer->results[0]->kind);
        $this->assertCount(2, $consumer->results[0]->children);
    }

    #[TestDox('produces function symbol')]
    public function testFunction(): void
    {
        $context = $this->createContext('<?php function myFunc() {}');
        $consumer = new DocumentSymbolConsumer();

        (new AstDocumentSymbolContributor())->contribute($context, $consumer);

        $this->assertCount(1, $consumer->results);
        $this->assertSame('myFunc', $consumer->results[0]->name);
        $this->assertSame(SymbolKind::FunctionKind, $consumer->results[0]->kind);
    }

    #[TestDox('handles namespaced declarations')]
    public function testNamespaced(): void
    {
        $context = $this->createContext('<?php namespace App; class Foo {} function bar() {}');
        $consumer = new DocumentSymbolConsumer();

        (new AstDocumentSymbolContributor())->contribute($context, $consumer);

        $this->assertCount(2, $consumer->results);
        $names = array_map(fn($s) => $s->name, $consumer->results);
        $this->assertContains('Foo', $names);
        $this->assertContains('bar', $names);
    }

    #[TestDox('returns empty for file without symbols')]
    public function testEmpty(): void
    {
        $context = $this->createContext('<?php echo 1;');
        $consumer = new DocumentSymbolConsumer();

        (new AstDocumentSymbolContributor())->contribute($context, $consumer);

        $this->assertEmpty($consumer->results);
    }
}

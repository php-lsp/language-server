<?php

declare(strict_types=1);

namespace App\Tests\Unit\Controller\TextDocument;

use App\Controller\TextDocument\DocumentSymbolController;
use App\Core\Contracts\PsiFile\PsiFileManagerInterface;
use App\Module\DocumentSymbol\AstDocumentSymbolContributor;
use App\Module\Telemetry\TracerInterface;
use App\Tests\Support\MockHelper;
use App\Tests\Support\ProtocolFactory;
use App\Tests\Support\PsiFileFactory;
use App\Tests\TestCase;
use Lsp\Extension\DocumentManager\Editor\EditorInterface;
use Lsp\Protocol\Type\DocumentSymbol;
use Lsp\Protocol\Type\DocumentSymbolParams;
use Lsp\Protocol\Type\SymbolKind;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\TestDox;

#[Group('unit')]
final class DocumentSymbolControllerTest extends TestCase
{
    #[TestDox('returns class symbol with methods and properties')]
    public function testReturnsClassSymbol(): void
    {
        $psiFile = PsiFileFactory::fromCode('<?php class Foo { public $bar; public function baz() {} }');
        $fileManager = MockHelper::mock(PsiFileManagerInterface::class);
        $fileManager->method('findPsiFile')->willReturn($psiFile);
        $tracer = MockHelper::mock(TracerInterface::class);
        $tracer->method('trace')->willReturnCallback(fn(string $name, callable $fn) => $fn());

        $controller = new DocumentSymbolController(
            [new AstDocumentSymbolContributor()],
            $fileManager,
            $tracer,
        );
        $editor = MockHelper::mock(EditorInterface::class);
        $params = new DocumentSymbolParams(
            textDocument: ProtocolFactory::textDocumentIdentifier(),
        );

        $result = $controller($editor, $params);

        $this->assertCount(1, $result);
        $this->assertInstanceOf(DocumentSymbol::class, $result[0]);
        $this->assertSame('Foo', $result[0]->name);
        $this->assertSame(SymbolKind::ClassKind, $result[0]->kind);

        $children = $result[0]->children;
        $this->assertCount(2, $children);

        $names = array_map(fn(DocumentSymbol $s) => $s->name, $children);
        $this->assertContains('$bar', $names);
        $this->assertContains('baz', $names);
    }

    #[TestDox('returns function symbol')]
    public function testReturnsFunctionSymbol(): void
    {
        $psiFile = PsiFileFactory::fromCode('<?php function myFunc() {}');
        $fileManager = MockHelper::mock(PsiFileManagerInterface::class);
        $fileManager->method('findPsiFile')->willReturn($psiFile);
        $tracer = MockHelper::mock(TracerInterface::class);
        $tracer->method('trace')->willReturnCallback(fn(string $name, callable $fn) => $fn());

        $controller = new DocumentSymbolController(
            [new AstDocumentSymbolContributor()],
            $fileManager,
            $tracer,
        );
        $editor = MockHelper::mock(EditorInterface::class);
        $params = new DocumentSymbolParams(
            textDocument: ProtocolFactory::textDocumentIdentifier(),
        );

        $result = $controller($editor, $params);

        $this->assertCount(1, $result);
        $this->assertSame('myFunc', $result[0]->name);
        $this->assertSame(SymbolKind::FunctionKind, $result[0]->kind);
    }

    #[TestDox('returns empty for file without classes or functions')]
    public function testReturnsEmptyForEmptyFile(): void
    {
        $psiFile = PsiFileFactory::fromCode('<?php echo 1;');
        $fileManager = MockHelper::mock(PsiFileManagerInterface::class);
        $fileManager->method('findPsiFile')->willReturn($psiFile);
        $tracer = MockHelper::mock(TracerInterface::class);
        $tracer->method('trace')->willReturnCallback(fn(string $name, callable $fn) => $fn());

        $controller = new DocumentSymbolController(
            [new AstDocumentSymbolContributor()],
            $fileManager,
            $tracer,
        );
        $editor = MockHelper::mock(EditorInterface::class);
        $params = new DocumentSymbolParams(
            textDocument: ProtocolFactory::textDocumentIdentifier(),
        );

        $result = $controller($editor, $params);

        $this->assertSame([], $result);
    }
}

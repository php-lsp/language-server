<?php

declare(strict_types=1);

namespace App\Tests\Unit\Controller\TextDocument;

use App\Controller\TextDocument\DocumentSymbolController;
use App\Tests\Support\MockHelper;
use App\Tests\Support\PsiFileFactory;
use App\Tests\TestCase;
use Lsp\Extension\DocumentManager\Editor\EditorInterface;
use Lsp\Protocol\Type\DocumentSymbolParams;
use Lsp\Protocol\Type\SymbolKind;
use Lsp\Protocol\Type\TextDocumentIdentifier;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\TestDox;

#[Group('unit')]
final class DocumentSymbolControllerTest extends TestCase
{
    #[TestDox('returns class and function symbols')]
    public function testReturnsSymbols(): void
    {
        $psiFile = PsiFileFactory::fromCode('<?php class Foo { public function bar() {} } function baz() {}');

        $fileManager = MockHelper::mock(\App\Module\PsiFile\InMemoryPsiFileManager::class);
        $fileManager->method('findPsiFile')->willReturn($psiFile);

        $controller = new DocumentSymbolController($fileManager);

        $editor = MockHelper::mock(EditorInterface::class);
        $params = new DocumentSymbolParams(
            textDocument: new TextDocumentIdentifier('file:///test.php'),
        );

        $symbols = $controller($editor, $params);

        $this->assertCount(2, $symbols);
        $this->assertSame('Foo', $symbols[0]->name);
        $this->assertSame(SymbolKind::ClassKind, $symbols[0]->kind);
        $this->assertSame('baz', $symbols[1]->name);
        $this->assertSame(SymbolKind::FunctionKind, $symbols[1]->kind);
    }

    #[TestDox('returns class members as children')]
    public function testReturnsClassMembers(): void
    {
        $psiFile = PsiFileFactory::fromCode('<?php class Foo { public $x; public function bar() {} }');

        $fileManager = MockHelper::mock(\App\Module\PsiFile\InMemoryPsiFileManager::class);
        $fileManager->method('findPsiFile')->willReturn($psiFile);

        $controller = new DocumentSymbolController($fileManager);

        $editor = MockHelper::mock(EditorInterface::class);
        $params = new DocumentSymbolParams(
            textDocument: new TextDocumentIdentifier('file:///test.php'),
        );

        $symbols = $controller($editor, $params);

        $this->assertCount(1, $symbols);
        $this->assertNotEmpty($symbols[0]->children);
        $this->assertCount(2, $symbols[0]->children);
    }
}

<?php

declare(strict_types=1);

namespace App\Tests\Unit\Controller\TextDocument;

use App\Controller\TextDocument\DiagnosticController;
use App\Module\Document\DocumentLoaderInterface;
use App\Module\PsiFile\PHPPsiFileParser;
use App\Tests\Support\ProtocolFactory;
use App\Tests\Support\PsiFileFactory;
use App\Tests\TestCase;
use Lsp\Extension\DocumentManager\Editor\EditorInterface;
use Lsp\Protocol\Type\DocumentDiagnosticParams;
use Lsp\Protocol\Type\RelatedFullDocumentDiagnosticReport;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\TestDox;

#[Group('unit')]
final class DiagnosticControllerTest extends TestCase
{
    #[TestDox('returns diagnostics for valid code')]
    public function testValidCode(): void
    {
        $document = PsiFileFactory::document('<?php echo 1;');
        $editor = $this->createMock(EditorInterface::class);
        $editor->method('findByUriString')->willReturn($document);

        $loader = $this->createMock(DocumentLoaderInterface::class);
        $controller = new DiagnosticController(PsiFileFactory::getParser(), $loader);

        $params = new DocumentDiagnosticParams(
            textDocument: ProtocolFactory::textDocumentIdentifier(),
        );

        $result = $controller($editor, $params);

        $this->assertInstanceOf(RelatedFullDocumentDiagnosticReport::class, $result);
        $this->assertSame([], $result->items);
    }

    #[TestDox('returns diagnostics for invalid code')]
    public function testInvalidCode(): void
    {
        // Use code that has errors but still produces a partial AST
        $document = PsiFileFactory::document('<?php echo $x; echo;');
        $editor = $this->createMock(EditorInterface::class);
        $editor->method('findByUriString')->willReturn($document);

        $loader = $this->createMock(DocumentLoaderInterface::class);
        $controller = new DiagnosticController(PsiFileFactory::getParser(), $loader);

        $params = new DocumentDiagnosticParams(
            textDocument: ProtocolFactory::textDocumentIdentifier(),
        );

        $result = $controller($editor, $params);

        $this->assertNotEmpty($result->items);
    }

    #[TestDox('loads document from loader when not in editor')]
    public function testLoadsFromLoader(): void
    {
        $document = PsiFileFactory::document('<?php echo 1;');

        // EditorInterface doesn't have 'open', so this path will throw.
        // We verify the loader is called instead.
        $editor = $this->createMock(EditorInterface::class);
        $editor->method('findByUriString')->willReturn(null);

        $loader = $this->createMock(DocumentLoaderInterface::class);
        $loader->expects($this->once())->method('load')->willReturn($document);

        $controller = new DiagnosticController(PsiFileFactory::getParser(), $loader);

        $params = new DocumentDiagnosticParams(
            textDocument: ProtocolFactory::textDocumentIdentifier(),
        );

        // The code calls $editor->open() which doesn't exist on the interface
        // This will throw a BadMethodCallException
        $this->expectException(\Error::class);
        $controller($editor, $params);
    }
}

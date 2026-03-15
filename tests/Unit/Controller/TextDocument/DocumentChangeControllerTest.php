<?php

declare(strict_types=1);

namespace App\Tests\Unit\Controller\TextDocument;

use App\Controller\TextDocument\DocumentChangeController;
use App\Core\Event\Document\DocumentChanged;
use App\Tests\Support\PsiFileFactory;
use App\Tests\TestCase;
use Lsp\Extension\DocumentManager\Editor\EditorInterface;
use Lsp\Protocol\Type\DidChangeTextDocumentParams;
use Lsp\Protocol\Type\TextDocumentContentChangeWholeDocument;
use Lsp\Protocol\Type\VersionedTextDocumentIdentifier;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\TestDox;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

#[Group('unit')]
final class DocumentChangeControllerTest extends TestCase
{
    #[TestDox('applies changes and dispatches DocumentChanged event')]
    public function testDispatchesDocumentChangedEvent(): void
    {
        $document = PsiFileFactory::document('<?php echo 1;', 'file:///test.php');

        $editor = $this->createMock(EditorInterface::class);
        $editor->method('findByUriString')
            ->with('file:///test.php')
            ->willReturn($document);

        $dispatcher = $this->createMock(EventDispatcherInterface::class);
        $dispatcher->expects($this->once())
            ->method('dispatch')
            ->with($this->callback(
                static fn(mixed $event) => $event instanceof DocumentChanged
                    && $event->document === $document,
            ));

        $controller = new DocumentChangeController($dispatcher);

        $params = new DidChangeTextDocumentParams(
            textDocument: new VersionedTextDocumentIdentifier(
                uri: 'file:///test.php',
                version: 2,
            ),
            contentChanges: [
                new TextDocumentContentChangeWholeDocument(text: '<?php echo 2;'),
            ],
        );

        $controller->__invoke($editor, $params);
    }

    #[TestDox('does not dispatch event when document not found')]
    public function testNoEventWhenDocumentNotFound(): void
    {
        $editor = $this->createMock(EditorInterface::class);
        $editor->method('findByUriString')->willReturn(null);

        $dispatcher = $this->createMock(EventDispatcherInterface::class);
        $dispatcher->expects($this->never())->method('dispatch');

        $controller = new DocumentChangeController($dispatcher);

        $params = new DidChangeTextDocumentParams(
            textDocument: new VersionedTextDocumentIdentifier(
                uri: 'file:///missing.php',
                version: 2,
            ),
            contentChanges: [
                new TextDocumentContentChangeWholeDocument(text: '<?php'),
            ],
        );

        $controller->__invoke($editor, $params);
    }
}

<?php

declare(strict_types=1);

namespace App\Tests\Unit\Controller\TextDocument;

use App\Controller\TextDocument\DocumentSaveController;
use App\Core\Event\Document\DocumentSaved;
use App\Tests\Support\PsiFileFactory;
use App\Tests\TestCase;
use Lsp\Extension\DocumentManager\Editor\EditorInterface;
use Lsp\Protocol\Type\DidSaveTextDocumentParams;
use Lsp\Protocol\Type\TextDocumentIdentifier;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\TestDox;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

#[Group('unit')]
final class DocumentSaveControllerTest extends TestCase
{
    #[TestDox('dispatches DocumentSaved event')]
    public function testDispatchesDocumentSavedEvent(): void
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
                static fn(mixed $event) => $event instanceof DocumentSaved
                    && $event->document === $document,
            ));

        $controller = new DocumentSaveController($dispatcher);

        $params = new DidSaveTextDocumentParams(
            textDocument: new TextDocumentIdentifier(uri: 'file:///test.php'),
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

        $controller = new DocumentSaveController($dispatcher);

        $params = new DidSaveTextDocumentParams(
            textDocument: new TextDocumentIdentifier(uri: 'file:///missing.php'),
        );

        $controller->__invoke($editor, $params);
    }
}

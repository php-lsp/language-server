<?php

declare(strict_types=1);

namespace App\Tests\Unit\Controller\TextDocument;

use App\Controller\TextDocument\DocumentCloseController;
use App\Core\Event\Document\DocumentClosed;
use App\Tests\Support\PsiFileFactory;
use App\Tests\TestCase;
use Lsp\Extension\DocumentManager\Editor\MutableEditorInterface;
use Lsp\Protocol\Type\DidCloseTextDocumentParams;
use Lsp\Protocol\Type\TextDocumentIdentifier;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\TestDox;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

#[Group('unit')]
final class DocumentCloseControllerTest extends TestCase
{
    #[TestDox('closes document and dispatches DocumentClosed event')]
    public function testDispatchesDocumentClosedEvent(): void
    {
        $document = PsiFileFactory::document('<?php echo 1;', 'file:///test.php');

        $editor = $this->createMock(MutableEditorInterface::class);
        $editor->method('findByUriString')
            ->with('file:///test.php')
            ->willReturn($document);
        $editor->expects($this->once())
            ->method('close')
            ->with($document);

        $dispatcher = $this->createMock(EventDispatcherInterface::class);
        $dispatcher->expects($this->once())
            ->method('dispatch')
            ->with($this->callback(
                static fn(mixed $event) => $event instanceof DocumentClosed
                    && $event->document === $document,
            ));

        $controller = new DocumentCloseController($dispatcher);

        $params = new DidCloseTextDocumentParams(
            textDocument: new TextDocumentIdentifier(uri: 'file:///test.php'),
        );

        $controller->__invoke($editor, $params);
    }

    #[TestDox('does not dispatch event when document not found')]
    public function testNoEventWhenDocumentNotFound(): void
    {
        $editor = $this->createMock(MutableEditorInterface::class);
        $editor->method('findByUriString')->willReturn(null);
        $editor->expects($this->never())->method('close');

        $dispatcher = $this->createMock(EventDispatcherInterface::class);
        $dispatcher->expects($this->never())->method('dispatch');

        $controller = new DocumentCloseController($dispatcher);

        $params = new DidCloseTextDocumentParams(
            textDocument: new TextDocumentIdentifier(uri: 'file:///missing.php'),
        );

        $controller->__invoke($editor, $params);
    }
}

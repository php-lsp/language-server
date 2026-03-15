<?php

declare(strict_types=1);

namespace App\Tests\Unit\Controller\TextDocument;

use App\Controller\TextDocument\DocumentOpenController;
use App\Core\Event\Document\DocumentOpened;
use App\Tests\Support\PsiFileFactory;
use App\Tests\TestCase;
use Lsp\Extension\DocumentManager\Editor\Document\DocumentFactoryInterface;
use Lsp\Extension\DocumentManager\Editor\MutableEditorInterface;
use Lsp\Protocol\Type\DidOpenTextDocumentParams;
use Lsp\Protocol\Type\TextDocumentItem;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\TestDox;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

#[Group('unit')]
final class DocumentOpenControllerTest extends TestCase
{
    #[TestDox('opens document in editor and dispatches DocumentOpened event')]
    public function testDispatchesDocumentOpenedEvent(): void
    {
        $document = PsiFileFactory::document('<?php echo 1;', 'file:///test.php');

        $factory = $this->createMock(DocumentFactoryInterface::class);
        $factory->expects($this->once())
            ->method('create')
            ->willReturn($document);

        $editor = $this->createMock(MutableEditorInterface::class);
        $editor->expects($this->once())
            ->method('open')
            ->with($document);

        $dispatcher = $this->createMock(EventDispatcherInterface::class);
        $dispatcher->expects($this->once())
            ->method('dispatch')
            ->with($this->callback(
                static fn(mixed $event) => $event instanceof DocumentOpened
                    && $event->document === $document,
            ));

        $controller = new DocumentOpenController($factory, $dispatcher);

        $params = new DidOpenTextDocumentParams(
            textDocument: new TextDocumentItem(
                uri: 'file:///test.php',
                languageId: 'php',
                version: 1,
                text: '<?php echo 1;',
            ),
        );

        $controller->__invoke($editor, $params);
    }
}

<?php

declare(strict_types=1);

namespace App\Controller\TextDocument;

use App\Core\Event\Document\DocumentClosed;
use Lsp\Extension\DocumentManager\Editor\MutableEditorInterface;
use Lsp\Kernel\Attribute\AsController;
use Lsp\Protocol\Type\DidCloseTextDocumentParams;
use Lsp\Router\Attribute\Route;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

#[AsController, Route('textDocument/didClose')]
final class DocumentCloseController
{
    public function __construct(
        private readonly EventDispatcherInterface $dispatcher,
    ) {}

    public function __invoke(
        MutableEditorInterface $editor,
        DidCloseTextDocumentParams $request,
    ): void {
        $document = $editor->findByUriString($request->textDocument->uri);

        if ($document === null) {
            return;
        }

        $editor->close($document);

        $this->dispatcher->dispatch(new DocumentClosed($document));
    }
}

<?php

declare(strict_types=1);

namespace App\Controller\TextDocument;

use App\Core\Event\Document\DocumentSaved;
use Lsp\Extension\DocumentManager\Editor\EditorInterface;
use Lsp\Kernel\Attribute\AsController;
use Lsp\Protocol\Type\DidSaveTextDocumentParams;
use Lsp\Router\Attribute\Route;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

#[AsController, Route('textDocument/didSave')]
final class DocumentSaveController
{
    public function __construct(
        private readonly EventDispatcherInterface $dispatcher,
    ) {}

    public function __invoke(
        EditorInterface $editor,
        DidSaveTextDocumentParams $request,
    ): void {
        $document = $editor->findByUriString($request->textDocument->uri);

        if ($document === null) {
            return;
        }

        $this->dispatcher->dispatch(new DocumentSaved($document));
    }
}

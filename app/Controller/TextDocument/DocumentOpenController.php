<?php

declare(strict_types=1);

namespace App\Controller\TextDocument;

use App\Core\Event\Document\DocumentOpened;
use Lsp\Extension\DocumentManager\Editor\Document\DocumentFactoryInterface;
use Lsp\Extension\DocumentManager\Editor\MutableEditorInterface;
use Lsp\Kernel\Attribute\AsController;
use Lsp\Protocol\Type\DidOpenTextDocumentParams;
use Lsp\Router\Attribute\Route;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

#[AsController, Route('textDocument/didOpen')]
final class DocumentOpenController
{
    public function __construct(
        private readonly DocumentFactoryInterface $documents,
        private readonly EventDispatcherInterface $dispatcher,
    ) {}

    public function __invoke(
        MutableEditorInterface $editor,
        DidOpenTextDocumentParams $request,
    ): void {
        $document = $this->documents->create(
            uri: $request->textDocument->uri,
            content: $request->textDocument->text,
            version: $request->textDocument->version,
        );

        $editor->open($document);

        $this->dispatcher->dispatch(new DocumentOpened($document));
    }
}

<?php

declare(strict_types=1);

namespace App\Listener;

use App\Core\Event\Document\DocumentChanged;
use App\Core\Event\Document\DocumentClosed;
use App\Core\Event\Document\DocumentOpened;
use App\Core\Event\Document\DocumentSaved;
use App\Module\PsiFile\InMemoryPsiFileManager;
use App\Module\PsiFile\Tree;
use Lsp\Extension\DocumentManager\Editor\Document\Document;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

/**
 * Invalidates in-memory PSI file cache and line offset cache
 * when documents are opened, changed, closed, or saved.
 */
final class DocumentCacheListener
{
    public function __construct(
        private readonly InMemoryPsiFileManager $psiFileManager,
    ) {}

    #[AsEventListener]
    public function onDocumentOpened(DocumentOpened $event): void
    {
        $this->invalidate($event->document);
    }

    #[AsEventListener]
    public function onDocumentChanged(DocumentChanged $event): void
    {
        $this->invalidate($event->document);
    }

    #[AsEventListener]
    public function onDocumentClosed(DocumentClosed $event): void
    {
        $this->invalidate($event->document);
    }

    #[AsEventListener]
    public function onDocumentSaved(DocumentSaved $event): void
    {
        $this->invalidate($event->document);
    }

    private function invalidate(Document $document): void
    {
        $this->psiFileManager->invalidate((string) $document->uri);
        Tree::clearLineOffsetCache();
    }
}

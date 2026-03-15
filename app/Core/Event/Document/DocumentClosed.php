<?php

declare(strict_types=1);

namespace App\Core\Event\Document;

use Lsp\Extension\DocumentManager\Editor\Document\Document;

final readonly class DocumentClosed
{
    public function __construct(
        public Document $document,
    ) {}
}

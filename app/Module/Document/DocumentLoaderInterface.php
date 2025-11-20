<?php
declare(strict_types=1);

namespace App\Module\Document;

use Lsp\Extension\DocumentManager\Editor\Document\Document;
use Lsp\Protocol\Type\TextDocumentIdentifier;

interface DocumentLoaderInterface
{
    public function load(TextDocumentIdentifier $identifier): Document;
}

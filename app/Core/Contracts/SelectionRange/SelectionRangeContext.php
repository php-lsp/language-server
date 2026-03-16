<?php

declare(strict_types=1);

namespace App\Core\Contracts\SelectionRange;

use Lsp\Extension\DocumentManager\Editor\EditorInterface;
use Lsp\Protocol\Type\Position;
use Lsp\Protocol\Type\TextDocumentIdentifier;

readonly class SelectionRangeContext
{
    public function __construct(
        public TextDocumentIdentifier $textDocumentIdentifier,
        /**
         * @var list<Position>
         */
        public array $positions,
        public EditorInterface $editor,
    ) {}
}

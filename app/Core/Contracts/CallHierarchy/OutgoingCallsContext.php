<?php

declare(strict_types=1);

namespace App\Core\Contracts\CallHierarchy;

use Lsp\Extension\DocumentManager\Editor\EditorInterface;
use Lsp\Protocol\Type\CallHierarchyItem;

readonly class OutgoingCallsContext
{
    public function __construct(
        public CallHierarchyItem $item,
        public EditorInterface $editor,
    ) {}
}

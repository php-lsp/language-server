<?php

declare(strict_types=1);

namespace App\Core\Contracts\Highlight;

interface DocumentHighlightContributor
{
    public function contribute(DocumentHighlightContext $context, DocumentHighlightConsumer $consumer): void;
}

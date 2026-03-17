<?php

declare(strict_types=1);

namespace App\Core\Contracts\DocumentSymbol;

interface DocumentSymbolContributor
{
    public function contribute(DocumentSymbolContext $context, DocumentSymbolConsumer $consumer): void;
}

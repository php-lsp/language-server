<?php
declare(strict_types=1);

namespace App\Core\Contracts\Documentation;

interface DocumentationContributor
{
    public function contribute(DocumentationContext $context, DocumentationConsumer $consumer): void;
}

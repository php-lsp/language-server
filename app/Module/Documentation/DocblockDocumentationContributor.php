<?php

declare(strict_types=1);

namespace App\Module\Documentation;

use App\Core\Contracts\Documentation\AsDocumentationContributor;
use App\Core\Contracts\Documentation\DocumentationConsumer;
use App\Core\Contracts\Documentation\DocumentationContext;
use App\Core\Contracts\Documentation\DocumentationContributor;
use App\Module\TypeSystem\TypeResolverInterface;
use App\Module\TypeSystem\TypeResult;

#[AsDocumentationContributor]
final class DocblockDocumentationContributor implements DocumentationContributor
{
    public function __construct(
        private readonly TypeResolverInterface $typeResolver,
    ) {}

    public function contribute(DocumentationContext $context, DocumentationConsumer $consumer): void
    {
        $result = $this->typeResolver->resolveAtPosition(
            $context->editor,
            $context->textDocumentIdentifier,
            $context->position,
        );

        if ($result === null) {
            return;
        }

        $consumer(sprintf(
            "```php\n%s\n```\n\n%s",
            $result->describeShort(),
            $result->describe(),
        ));
    }
}

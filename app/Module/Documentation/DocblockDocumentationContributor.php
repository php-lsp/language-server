<?php

declare(strict_types=1);

namespace App\Module\Documentation;

use App\Controller\PHPStanAnalyzer;
use App\Core\Contracts\Documentation\AsDocumentationContributor;
use App\Core\Contracts\Documentation\DocumentationConsumer;
use App\Core\Contracts\Documentation\DocumentationContext;
use App\Core\Contracts\Documentation\DocumentationContributor;
use App\Module\Indexing\Indexer\ClassIndexer;
use App\Module\Indexing\IndexLookup;
use PHPStan\Type\Type;
use PHPStan\Type\VerbosityLevel;

#[AsDocumentationContributor]
final class DocblockDocumentationContributor implements DocumentationContributor
{
    public function __construct(
        private PHPStanAnalyzer $analyzer,
        private readonly IndexLookup $indexLookup,
    )
    {
    }

    public function contribute(DocumentationContext $context, DocumentationConsumer $consumer): void
    {
        $type = $this->analyzer->getTypeAtPosition(
            $context->editor,
            $context->textDocumentIdentifier,
            $context->position,
        );

        if ($type !== null) {
            $consumer($this->formatType($type));
        }

//        foreach ($this->indexLookup->findByKey(ClassIndexer::class) as $key => $value) {
//            $consumer();
//        }
    }

    private function formatType(Type $type): string
    {
        return sprintf(
            <<<MARKDOWN
            PHPStan Type:
            %s (%s)
            MARKDOWN,
            $type->describe(VerbosityLevel::precise()),
            $type->describe(VerbosityLevel::typeOnly())
        );
    }
}

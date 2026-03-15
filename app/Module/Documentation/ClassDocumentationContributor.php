<?php

declare(strict_types=1);

namespace App\Module\Documentation;

use App\Core\Contracts\Documentation\AsDocumentationContributor;
use App\Core\Contracts\Documentation\DocumentationConsumer;
use App\Core\Contracts\Documentation\DocumentationContext;
use App\Core\Contracts\Documentation\DocumentationContributor;
use App\Module\Indexing\Indexer\ClassIndexer;
use App\Module\Indexing\Indexer\EnumIndexer;
use App\Module\Indexing\Indexer\InterfaceIndexer;
use App\Module\Indexing\Indexer\TraitIndexer;
use App\Module\Indexing\IndexLookup;
use App\Module\PsiFile\InMemoryPsiFileManager;
use Override;
use PhpParser\Node;

#[AsDocumentationContributor]
final class ClassDocumentationContributor implements DocumentationContributor
{
    public function __construct(
        private readonly IndexLookup $indexLookup,
        private readonly InMemoryPsiFileManager $fileManager,
    ) {}

    #[Override]
    public function contribute(DocumentationContext $context, DocumentationConsumer $consumer): void
    {
        $file = $this->fileManager->findPsiFile($context->editor, $context->textDocumentIdentifier);
        if ($file === null) {
            return;
        }

        $element = $file->findLastAtPosition($context->position);
        if (!$element instanceof Node\Name\FullyQualified) {
            return;
        }

        $name = $element->toString();

        foreach ($this->indexLookup->findByKey(ClassIndexer::class) as $entry) {
            if ($entry->value->fqn !== $name) {
                continue;
            }

            $parts = [];
            if ($entry->value->isAbstract) {
                $parts[] = 'abstract';
            }
            if ($entry->value->isFinal) {
                $parts[] = 'final';
            }
            $parts[] = 'class';
            $parts[] = $entry->value->fqn;

            $signature = implode(' ', $parts);
            if ($entry->value->extends !== null) {
                $signature .= ' extends ' . $entry->value->extends;
            }
            if ($entry->value->implements !== []) {
                $signature .= ' implements ' . implode(', ', $entry->value->implements);
            }

            $consumer(sprintf("```php\n%s\n```", $signature));

            return;
        }

        foreach ($this->indexLookup->findByKey(InterfaceIndexer::class) as $entry) {
            if ($entry->value->fqn !== $name) {
                continue;
            }

            $signature = 'interface ' . $entry->value->fqn;
            if ($entry->value->extends !== []) {
                $signature .= ' extends ' . implode(', ', $entry->value->extends);
            }

            $consumer(sprintf("```php\n%s\n```", $signature));

            return;
        }

        foreach ($this->indexLookup->findByKey(TraitIndexer::class) as $entry) {
            if ($entry->value->fqn !== $name) {
                continue;
            }

            $consumer(sprintf("```php\ntrait %s\n```", $entry->value->fqn));

            return;
        }

        foreach ($this->indexLookup->findByKey(EnumIndexer::class) as $entry) {
            if ($entry->value->fqn !== $name) {
                continue;
            }

            $signature = 'enum ' . $entry->value->fqn;
            if ($entry->value->backedType !== null) {
                $signature .= ': ' . $entry->value->backedType;
            }
            if ($entry->value->implements !== []) {
                $signature .= ' implements ' . implode(', ', $entry->value->implements);
            }

            $consumer(sprintf("```php\n%s\n```", $signature));

            return;
        }
    }
}

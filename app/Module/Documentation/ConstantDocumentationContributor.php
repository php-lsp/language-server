<?php

declare(strict_types=1);

namespace App\Module\Documentation;

use App\Core\Contracts\Documentation\AsDocumentationContributor;
use App\Core\Contracts\Documentation\DocumentationConsumer;
use App\Core\Contracts\Documentation\DocumentationContext;
use App\Core\Contracts\Documentation\DocumentationContributor;
use App\Module\Indexing\Indexer\ClassConstantIndexer;
use App\Module\Indexing\Indexer\GlobalConstantIndexer;
use App\Module\Indexing\IndexLookup;
use App\Module\PsiFile\InMemoryPsiFileManager;
use App\Module\PsiFile\Tree;
use Override;
use PhpParser\Node;

#[AsDocumentationContributor]
final class ConstantDocumentationContributor implements DocumentationContributor
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

        // Class constant: Foo::BAR
        if ($element instanceof Node\Identifier) {
            $constFetch = Tree::parentOfType($element, Node\Expr\ClassConstFetch::class);
            if ($constFetch !== null && $constFetch->class instanceof Node\Name) {
                $className = $constFetch->class->toString();
                $constName = $element->toString();

                foreach ($this->indexLookup->findByField(
                    ClassConstantIndexer::class,
                    'className',
                    $className,
                ) as $entry) {
                    if ($entry->value->name !== $constName) {
                        continue;
                    }

                    $signature = 'const ' . $className . '::' . $entry->value->name;
                    if ($entry->value->type !== null) {
                        $signature .= ': ' . $entry->value->type;
                    }

                    $consumer(sprintf("```php\n%s\n```", $signature));

                    return;
                }
            }
        }

        // Global constant
        if ($element instanceof Node\Name) {
            $parent = Tree::parent($element);
            if ($parent instanceof Node\Expr\ConstFetch) {
                $constName = $element->toString();
                if (in_array(strtolower($constName), ['true', 'false', 'null'], strict: true)) {
                    return;
                }

                foreach ($this->indexLookup->findByField(GlobalConstantIndexer::class, 'name', $constName) as $entry) {
                    $signature = 'const ' . $entry->value->name;

                    $consumer(sprintf("```php\n%s\n```", $signature));

                    return;
                }
            }
        }
    }
}

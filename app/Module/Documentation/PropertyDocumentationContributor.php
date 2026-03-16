<?php

declare(strict_types=1);

namespace App\Module\Documentation;

use App\Core\Contracts\Documentation\AsDocumentationContributor;
use App\Core\Contracts\Documentation\DocumentationConsumer;
use App\Core\Contracts\Documentation\DocumentationContext;
use App\Core\Contracts\Documentation\DocumentationContributor;
use App\Module\Indexing\Indexer\PropertyIndexer;
use App\Module\Indexing\IndexLookup;
use App\Module\PsiFile\InMemoryPsiFileManager;
use App\Module\PsiFile\Tree;
use Override;
use PhpParser\Node;

#[AsDocumentationContributor]
final class PropertyDocumentationContributor implements DocumentationContributor
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
        if (!$element instanceof Node\Identifier) {
            return;
        }

        $propertyFetch = Tree::parentOfType($element, Node\Expr\PropertyFetch::class);
        if ($propertyFetch === null) {
            return;
        }

        $propertyName = $element->toString();
        $className = null;
        if ($propertyFetch->var instanceof Node\Expr\Variable && $propertyFetch->var->name === 'this') {
            $classNode = Tree::parentOfType($element, Node\Stmt\Class_::class);
            if ($classNode !== null) {
                $className = $classNode->namespacedName?->toString() ?? $classNode->name?->toString();
            }
        }

        if ($className === null) {
            return;
        }

        foreach ($this->indexLookup->findByField(PropertyIndexer::class, 'className', $className) as $entry) {
            if ($entry->value->name !== $propertyName) {
                continue;
            }

            $parts = [$entry->value->visibility->value];
            if ($entry->value->isStatic) {
                $parts[] = 'static';
            }
            if ($entry->value->isReadonly) {
                $parts[] = 'readonly';
            }
            if ($entry->value->type !== null) {
                $parts[] = $entry->value->type;
            }
            $parts[] = '$' . $entry->value->name;

            $consumer(sprintf("```php\n%s::%s\n```", $className, implode(' ', $parts)));

            return;
        }
    }
}

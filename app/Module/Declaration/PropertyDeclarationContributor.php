<?php

declare(strict_types=1);

namespace App\Module\Declaration;

use App\Core\Contracts\Declaration\AsDeclarationContributor;
use App\Core\Contracts\Declaration\DeclarationConsumer;
use App\Core\Contracts\Declaration\DeclarationContext;
use App\Core\Contracts\Declaration\DeclarationContributor;
use App\Module\Indexing\Indexer\PropertyIndexer;
use App\Module\Indexing\IndexLookup;
use App\Module\PsiFile\InMemoryPsiFileManager;
use App\Module\PsiFile\PositionResolver;
use App\Module\PsiFile\Tree;
use Lsp\Protocol\Type\Location;
use Override;
use PhpParser\Node;

#[AsDeclarationContributor]
final class PropertyDeclarationContributor implements DeclarationContributor
{
    public function __construct(
        private readonly IndexLookup $indexLookup,
        private readonly InMemoryPsiFileManager $fileManager,
        private readonly PositionResolver $positionResolver,
    ) {}

    #[Override]
    public function contribute(DeclarationContext $context, DeclarationConsumer $consumer): void
    {
        $editor = $context->editor;
        $file = $this->fileManager->findPsiFile($editor, $context->textDocumentIdentifier);
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

        // Resolve the class for $this
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

            $consumer(new Location(
                uri: $entry->uri,
                range: $this->positionResolver->resolveRange(
                    $entry->uri,
                    $entry->value->startPosition,
                    $context->editor,
                ),
            ));
        }
    }
}

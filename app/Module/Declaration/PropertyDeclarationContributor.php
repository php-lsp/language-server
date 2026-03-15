<?php

declare(strict_types=1);

namespace App\Module\Declaration;

use App\Core\Contracts\Declaration\AsDeclarationContributor;
use App\Core\Contracts\Declaration\DeclarationConsumer;
use App\Core\Contracts\Declaration\DeclarationContext;
use App\Core\Contracts\Declaration\DeclarationContributor;
use App\Module\Document\DocumentIdentifierFactoryInterface;
use App\Module\Indexing\Indexer\PropertyIndexer;
use App\Module\Indexing\IndexLookup;
use App\Module\PsiFile\InMemoryPsiFileManager;
use App\Module\PsiFile\Tree;
use Lsp\Protocol\Type\Location;
use Lsp\Protocol\Type\Position;
use Lsp\Protocol\Type\Range;
use Override;
use PhpParser\Node;

#[AsDeclarationContributor]
final class PropertyDeclarationContributor implements DeclarationContributor
{
    public function __construct(
        private readonly IndexLookup $indexLookup,
        private readonly InMemoryPsiFileManager $fileManager,
        private readonly DocumentIdentifierFactoryInterface $documentIdentifierFactory,
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

        foreach ($this->indexLookup->findByKey(PropertyIndexer::class) as $entry) {
            if ($entry->value->className !== $className || $entry->value->name !== $propertyName) {
                continue;
            }

            $consumer(new Location(
                uri: $entry->uri,
                range: $this->positionToRange($entry->uri, $entry->value->startPosition, $context),
            ));
        }
    }

    private function positionToRange(string $uri, int $startPosition, DeclarationContext $context): Range
    {
        $textDocumentIdentifier = $this->documentIdentifierFactory->create($uri);
        $source = $this->fileManager->findPsiFile($context->editor, $textDocumentIdentifier);
        if ($source === null) {
            $zeroPosition = new Position(0, 0);

            return new Range($zeroPosition, $zeroPosition);
        }

        [$line, $column] = Tree::toLineColumn($source->ast->document, $startPosition);
        $exactPosition = new Position($line, $column);

        return new Range($exactPosition, $exactPosition);
    }
}

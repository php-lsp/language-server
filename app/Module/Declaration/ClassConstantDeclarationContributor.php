<?php

declare(strict_types=1);

namespace App\Module\Declaration;

use App\Core\Contracts\Declaration\AsDeclarationContributor;
use App\Core\Contracts\Declaration\DeclarationConsumer;
use App\Core\Contracts\Declaration\DeclarationContext;
use App\Core\Contracts\Declaration\DeclarationContributor;
use App\Module\Document\DocumentIdentifierFactoryInterface;
use App\Module\Indexing\Indexer\ClassConstantIndexer;
use App\Module\Indexing\IndexLookup;
use App\Module\PsiFile\InMemoryPsiFileManager;
use App\Module\PsiFile\Tree;
use Lsp\Protocol\Type\Location;
use Lsp\Protocol\Type\Position;
use Lsp\Protocol\Type\Range;
use Override;
use PhpParser\Node;

#[AsDeclarationContributor]
final class ClassConstantDeclarationContributor implements DeclarationContributor
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

        $constFetch = Tree::parentOfType($element, Node\Expr\ClassConstFetch::class);
        if ($constFetch === null) {
            return;
        }

        if (!$constFetch->class instanceof Node\Name) {
            return;
        }

        $className = $constFetch->class->toString();
        $constantName = $constFetch->name instanceof Node\Identifier ? $constFetch->name->toString() : null;
        if ($constantName === null) {
            return;
        }

        foreach ($this->indexLookup->findByKey(ClassConstantIndexer::class) as $entry) {
            if ($entry->value->ownerFqn !== $className || $entry->value->name !== $constantName) {
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

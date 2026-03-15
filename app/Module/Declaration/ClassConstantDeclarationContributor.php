<?php

declare(strict_types=1);

namespace App\Module\Declaration;

use App\Core\Contracts\Declaration\AsDeclarationContributor;
use App\Core\Contracts\Declaration\DeclarationConsumer;
use App\Core\Contracts\Declaration\DeclarationContext;
use App\Core\Contracts\Declaration\DeclarationContributor;
use App\Module\Indexing\Indexer\ClassConstantIndexer;
use App\Module\Indexing\IndexLookup;
use App\Module\PsiFile\InMemoryPsiFileManager;
use App\Module\PsiFile\PositionResolver;
use App\Module\PsiFile\Tree;
use Lsp\Protocol\Type\Location;
use Override;
use PhpParser\Node;

#[AsDeclarationContributor]
final class ClassConstantDeclarationContributor implements DeclarationContributor
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
                range: $this->positionResolver->resolveRange(
                    $entry->uri,
                    $entry->value->startPosition,
                    $context->editor,
                ),
            ));
        }
    }
}

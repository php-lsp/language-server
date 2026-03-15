<?php

declare(strict_types=1);

namespace App\Module\Declaration;

use App\Core\Contracts\Declaration\AsDeclarationContributor;
use App\Core\Contracts\Declaration\DeclarationConsumer;
use App\Core\Contracts\Declaration\DeclarationContext;
use App\Core\Contracts\Declaration\DeclarationContributor;
use App\Module\Indexing\Indexer\GlobalConstantIndexer;
use App\Module\Indexing\IndexLookup;
use App\Module\PsiFile\InMemoryPsiFileManager;
use App\Module\PsiFile\PositionResolver;
use App\Module\PsiFile\Tree;
use Lsp\Protocol\Type\Location;
use Override;
use PhpParser\Node;

#[AsDeclarationContributor]
final class GlobalConstantDeclarationContributor implements DeclarationContributor
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

        // Try to find a ConstFetch parent
        $constFetch = null;
        if ($element instanceof Node\Name) {
            $parent = Tree::parent($element);
            if ($parent instanceof Node\Expr\ConstFetch) {
                $constFetch = $parent;
            }
        }

        if ($constFetch === null) {
            return;
        }

        $constName = $constFetch->name->toString();

        // Skip built-in constants like true, false, null
        if (in_array(strtolower($constName), ['true', 'false', 'null'], strict: true)) {
            return;
        }

        foreach ($this->indexLookup->findByKey(GlobalConstantIndexer::class) as $entry) {
            if ($entry->value->name !== $constName) {
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

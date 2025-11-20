<?php

declare(strict_types=1);

namespace App\Module\Declaration;

use App\Core\Contracts\Declaration\AsDeclarationContributor;
use App\Core\Contracts\Declaration\DeclarationConsumer;
use App\Core\Contracts\Declaration\DeclarationContext;
use App\Core\Contracts\Declaration\DeclarationContributor;
use App\Module\Indexing\Indexer\ClassIndexer;
use App\Module\Indexing\IndexLookup;
use App\Module\PsiFile\InMemoryPsiFileManager;
use App\Module\PsiFile\Tree;
use Lsp\Protocol\Type\Location;
use Lsp\Protocol\Type\Position;
use Lsp\Protocol\Type\Range;
use PhpParser\Node;

#[AsDeclarationContributor]
final class ClassDeclarationContributor implements DeclarationContributor
{
    public function __construct(
        private readonly IndexLookup $indexLookup,
        private readonly InMemoryPsiFileManager $fileManager,
    )
    {
    }

    public function contribute(DeclarationContext $context, DeclarationConsumer $consumer): void
    {
        $editor = $context->editor;
        $file = $this->fileManager->findPsiFile($editor, $context->textDocumentIdentifier);
        if ($file === null) {
//            dump('file is null', $context->textDocumentIdentifier);
            return;
        }

        $element = $file->findLastAtPosition($context->position);
        if (!$element instanceof Node\Name\FullyQualified) {
            return;
        }

        $node = Tree::parentOfType($element, Node\Expr\ClassConstFetch::class);
        if ($node === null) {
            return;
        }

        $className = $node->class->toString();

        $zeroPosition = new Position(0, 0);
        $startRange = new Range($zeroPosition, $zeroPosition);

        foreach ($this->indexLookup->findByKey(ClassIndexer::class) as $value) {
            if ($value->value !== $className) {
                continue;
            }
            $consumer(new Location(
                uri: $value->uri,
                range: $startRange,
            ));
        }
    }
}

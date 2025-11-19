<?php

declare(strict_types=1);

namespace App\Module\Reference;

use App\Core\Contracts\References\AsReferenceContributor;
use App\Core\Contracts\References\ReferenceConsumer;
use App\Core\Contracts\References\ReferenceContext;
use App\Core\Contracts\References\ReferenceContributor;
use App\Module\Indexing\Indexer\ClassIndexer;
use App\Module\Indexing\IndexLookup;
use App\Module\PsiFile\InMemoryPsiFileManager;
use Lsp\Protocol\Type\Location;
use Lsp\Protocol\Type\Position;
use Lsp\Protocol\Type\Range;
use PhpParser\Node;
use PhpParser\Node\Expr\ClassConstFetch;

#[AsReferenceContributor]
final class ClassReferenceContributor implements ReferenceContributor
{
    public function __construct(
        private readonly IndexLookup $indexLookup,
        private readonly InMemoryPsiFileManager $fileManager,
    )
    {
    }

    public function contribute(ReferenceContext $context, ReferenceConsumer $consumer): void
    {
        $editor = $context->editor;
        $document = $editor->findByUriString($context->textDocumentIdentifier->uri);
        if ($document === null) {
            dump('document is null', $context->textDocumentIdentifier);
            return;
        }

        $file = $this->fileManager->findPsiFile($editor, $context->textDocumentIdentifier);
        if ($file === null) {
            dump('file is null', $context->textDocumentIdentifier);
            return;
        }

        $nodes = $file->findAtPosition($context->position);

        dump('$nodes', $nodes);

        /**
         * @var ClassConstFetch|null $node
         */
        $node = array_find($nodes, fn(Node $node) => match (true) {
            $node instanceof ClassConstFetch => true,
            default => false
        });
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

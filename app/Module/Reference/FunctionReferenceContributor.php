<?php

declare(strict_types=1);

namespace App\Module\Reference;

use App\Core\Contracts\References\AsReferenceContributor;
use App\Core\Contracts\References\ReferenceConsumer;
use App\Core\Contracts\References\ReferenceContext;
use App\Core\Contracts\References\ReferenceContributor;
use App\Module\Indexing\Indexer\FunctionIndexer;
use App\Module\Indexing\IndexLookup;
use App\Module\PsiFile\InMemoryPsiFileManager;
use App\Module\PsiFile\Tree;
use Lsp\Protocol\Type\Location;
use Lsp\Protocol\Type\Position;
use Lsp\Protocol\Type\Range;
use Lsp\Protocol\Type\TextDocumentIdentifier;
use PhpParser\Node;

#[AsReferenceContributor]
final class FunctionReferenceContributor implements ReferenceContributor
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
//            dump('document is null', $context->textDocumentIdentifier);
            return;
        }

        $file = $this->fileManager->findPsiFile($editor, $context->textDocumentIdentifier);
        if ($file === null) {
//            dump('file is null', $context->textDocumentIdentifier);
            return;
        }

        $nodes = $file->findAtPosition($context->position);

//        dump('$nodes', $nodes);

        /**
         * @var Node\Expr\FuncCall|null $node
         */
        $node = array_find($nodes, fn(Node $node) => match (true) {
            $node instanceof Node\Expr\FuncCall => true,
            default => false
        });
        if ($node === null) {
            return;
        }
        $functionName = $node->name->toString();

        foreach ($this->indexLookup->findByKey(FunctionIndexer::class) as $value) {
            if ($value->value[0] !== $functionName) {
                continue;
            }
            $source = $this->fileManager->findPsiFile($context->editor, new TextDocumentIdentifier($value->uri));
            $position = $value->value[1];

            [$line, $column] = Tree::toLineColumn($source->ast->document, $position);

            $exactPosition = new Position($line, $column);
            $startRange = new Range($exactPosition, $exactPosition);

            $consumer(new Location(
                uri: $value->uri,
                range: $startRange,
            ));
        }
    }
}

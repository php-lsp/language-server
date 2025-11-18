<?php

declare(strict_types=1);

namespace App\Module\Reference;

use App\Core\Contracts\Completion\AsCompletionContributor;
use App\Core\Contracts\CompletionConsumer;
use App\Core\Contracts\CompletionContext;
use App\Core\Contracts\CompletionContributor;
use App\Core\Contracts\Indexing\AsIndexer;
use App\Core\Contracts\References\AsReferenceContributor;
use App\Core\Contracts\References\ReferenceConsumer;
use App\Core\Contracts\References\ReferenceContext;
use App\Core\Contracts\References\ReferenceContributor;
use App\Module\Indexing\Indexer\ClassIndexer;
use App\Module\Indexing\Indexer\ClassMethodIndexer;
use App\Module\Indexing\IndexLookup;
use App\Module\PsiFile\InMemoryPsiFileManager;
use Lsp\Protocol\Type\CompletionItem;
use Lsp\Protocol\Type\CompletionItemKind;
use Lsp\Protocol\Type\CompletionParams;
use Lsp\Protocol\Type\Location;
use Lsp\Protocol\Type\Position;
use Lsp\Protocol\Type\Range;
use PhpParser\Node;
use PhpParser\Node\Expr\ClassConstFetch;

#[AsReferenceContributor]
final class ClassMethodReferenceContributor implements ReferenceContributor
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
         * @var Node\Expr\StaticCall|null $node
         */
        $node = array_find($nodes, fn(Node $node) => match (true) {
            $node instanceof Node\Expr\StaticCall => true,
//            $node instanceof Node\Expr\MethodCall => true,
            default => false
        });
        if ($node === null) {
            return;
        }
        $className = $node->class->toString();
        $methodName = $node->name->toString();

        $zeroPosition = new Position(0, 0);
        $startRange = new Range($zeroPosition, $zeroPosition);

        foreach ($this->indexLookup->findByKey(ClassMethodIndexer::class) as $value) {
            if ($value->key !== $className) {
                continue;
            }
            if (!isset($value->value[$methodName])) {
                continue;
            }

            $consumer(new Location(
                uri: $value->uri,
                range: $startRange,
            ));
        }
    }
}

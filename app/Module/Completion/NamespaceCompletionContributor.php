<?php

declare(strict_types=1);

namespace App\Module\Completion;

use App\Core\Contracts\Completion\AsCompletionContributor;
use App\Core\Contracts\Completion\CompletionConsumer;
use App\Core\Contracts\Completion\CompletionContext;
use App\Core\Contracts\Completion\CompletionContributor;
use App\Core\Contracts\PrefixMatcher\StrContainsMatcher;
use App\Module\Indexing\Indexer\NamespaceIndexer;
use App\Module\Indexing\IndexLookup;
use App\Module\PsiFile\Tree;
use Lsp\Protocol\Type\CompletionItem;
use Lsp\Protocol\Type\CompletionItemKind;
use Override;
use PhpParser\Node;

#[AsCompletionContributor]
final class NamespaceCompletionContributor implements CompletionContributor
{
    public function __construct(
        private readonly IndexLookup $indexLookup,
    ) {}

    #[Override]
    public function contribute(CompletionContext $context, CompletionConsumer $consumer): void
    {
        $element = $context->currentNode();
        if ($element === null) {
            return;
        }

        // Check if we're in a namespace declaration
        $namespace = Tree::parentOfType($element, Node\Stmt\Namespace_::class);
        if ($namespace === null) {
            return;
        }

        // Only trigger if cursor is on the namespace name
        if (
            $namespace->name !== $element
            && !Tree::parentOfType($element, Node\Name::class)?->getAttribute('parent') instanceof Node\Stmt\Namespace_
        ) {
            return;
        }

        $prefix = Tree::toString($element);
        $matcher = new StrContainsMatcher($prefix);

        foreach ($this->indexLookup->findByKey(NamespaceIndexer::class) as $entry) {
            if (!$matcher->match($entry->value->fqn)) {
                continue;
            }
            $consumer(new CompletionItem(
                label: $entry->value->fqn,
                kind: CompletionItemKind::ModuleKind,
                detail: '[namespace]',
            ));
        }
    }
}

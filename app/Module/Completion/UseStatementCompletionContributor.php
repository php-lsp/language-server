<?php

declare(strict_types=1);

namespace App\Module\Completion;

use App\Core\Contracts\Completion\AsCompletionContributor;
use App\Core\Contracts\Completion\CompletionConsumer;
use App\Core\Contracts\Completion\CompletionContext;
use App\Core\Contracts\Completion\CompletionContributor;
use App\Core\Contracts\PrefixMatcher\StrContainsMatcher;
use App\Module\Indexing\Indexer\ClassIndexer;
use App\Module\Indexing\Indexer\EnumIndexer;
use App\Module\Indexing\Indexer\InterfaceIndexer;
use App\Module\Indexing\Indexer\TraitIndexer;
use App\Module\Indexing\IndexLookup;
use App\Module\PsiFile\Tree;
use Lsp\Protocol\Type\CompletionItem;
use Lsp\Protocol\Type\CompletionItemKind;
use PhpParser\Node;

#[AsCompletionContributor]
final class UseStatementCompletionContributor implements CompletionContributor
{
    public function __construct(
        private readonly IndexLookup $indexLookup,
    ) {}

    public function contribute(CompletionContext $context, CompletionConsumer $consumer): void
    {
        $element = $context->currentNode();
        if ($element === null) {
            return;
        }

        // Check if we're in a use statement
        $useItem = Tree::parentOfType($element, Node\UseItem::class);
        if ($useItem === null) {
            return;
        }

        $prefix = Tree::toString($element);
        $matcher = new StrContainsMatcher($prefix);

        foreach ($this->indexLookup->findByKey(ClassIndexer::class) as $entry) {
            if (!$matcher->match($entry->value->fqn)) {
                continue;
            }
            $consumer(new CompletionItem(
                label: $entry->value->fqn,
                kind: CompletionItemKind::ClassKind,
                detail: '[class]',
            ));
        }

        foreach ($this->indexLookup->findByKey(InterfaceIndexer::class) as $entry) {
            if (!$matcher->match($entry->value->fqn)) {
                continue;
            }
            $consumer(new CompletionItem(
                label: $entry->value->fqn,
                kind: CompletionItemKind::InterfaceKind,
                detail: '[interface]',
            ));
        }

        foreach ($this->indexLookup->findByKey(TraitIndexer::class) as $entry) {
            if (!$matcher->match($entry->value->fqn)) {
                continue;
            }
            $consumer(new CompletionItem(
                label: $entry->value->fqn,
                kind: CompletionItemKind::ClassKind,
                detail: '[trait]',
            ));
        }

        foreach ($this->indexLookup->findByKey(EnumIndexer::class) as $entry) {
            if (!$matcher->match($entry->value->fqn)) {
                continue;
            }
            $consumer(new CompletionItem(
                label: $entry->value->fqn,
                kind: CompletionItemKind::EnumKind,
                detail: '[enum]',
            ));
        }
    }
}

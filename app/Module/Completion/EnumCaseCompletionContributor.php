<?php

declare(strict_types=1);

namespace App\Module\Completion;

use App\Core\Contracts\Completion\AsCompletionContributor;
use App\Core\Contracts\Completion\CompletionConsumer;
use App\Core\Contracts\Completion\CompletionContext;
use App\Core\Contracts\Completion\CompletionContributor;
use App\Module\Indexing\Indexer\ClassConstantIndexer;
use App\Module\Indexing\Indexer\EnumIndexer;
use App\Module\Indexing\IndexLookup;
use App\Module\PsiFile\Tree;
use Lsp\Protocol\Type\CompletionItem;
use Lsp\Protocol\Type\CompletionItemKind;
use PhpParser\Node;

#[AsCompletionContributor]
final class EnumCaseCompletionContributor implements CompletionContributor
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

        $enumName = null;

        $constFetch = Tree::parentOfType($element, Node\Expr\ClassConstFetch::class);
        if ($constFetch !== null && $constFetch->class instanceof Node\Name) {
            $name = $constFetch->class->toString();
            // Check if this name is an enum
            foreach ($this->indexLookup->findByKey(EnumIndexer::class) as $entry) {
                if ($entry->value->fqn === $name) {
                    $enumName = $name;
                    break;
                }
            }
        }

        if ($enumName === null) {
            return;
        }

        foreach ($this->indexLookup->findByKey(ClassConstantIndexer::class) as $entry) {
            if ($entry->value->ownerFqn !== $enumName) {
                continue;
            }

            $consumer(new CompletionItem(
                label: $entry->value->name,
                kind: CompletionItemKind::EnumMemberKind,
                detail: '[enum case]',
            ));
        }
    }
}

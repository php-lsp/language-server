<?php

declare(strict_types=1);

namespace App\Module\Completion;

use App\Core\Contracts\Completion\AsCompletionContributor;
use App\Core\Contracts\Completion\CompletionConsumer;
use App\Core\Contracts\Completion\CompletionContext;
use App\Core\Contracts\Completion\CompletionContributor;
use App\Core\Contracts\PrefixMatcher\StrContainsMatcher;
use App\Module\Indexing\Indexer\ClassIndexer;
use App\Module\Indexing\IndexLookup;
use App\Module\PsiFile\Tree;
use Lsp\Protocol\Type\CompletionItem;
use Lsp\Protocol\Type\CompletionItemKind;

#[AsCompletionContributor]
final class ClassesCompletionContributor implements CompletionContributor
{
    public function __construct(
        private readonly IndexLookup $indexLookup,
    ) {}

    public function contribute(CompletionContext $context, CompletionConsumer $consumer): void
    {
        $element = $context->currentNode();
        $string = Tree::toString($element);
        $matcher = new StrContainsMatcher($string);

        foreach ($this->indexLookup->findByKey(ClassIndexer::class) as $key => $value) {
            $fqn = $value->value->fqn;
            if (!$matcher->match($fqn)) {
                continue;
            }

            $consumer(new CompletionItem(
                label: $fqn,
                kind: CompletionItemKind::ClassKind,
                detail: '[class]',
            ));
        }
    }
}

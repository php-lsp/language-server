<?php

declare(strict_types=1);

namespace App\Module\Completion;

use App\Core\Contracts\Completion\AsCompletionContributor;
use App\Core\Contracts\CompletionConsumer;
use App\Core\Contracts\CompletionContext;
use App\Core\Contracts\CompletionContributor;
use App\Core\Contracts\Indexing\AsIndexer;
use App\Module\Indexing\Indexer\ClassIndexer;
use App\Module\Indexing\IndexLookup;
use Lsp\Protocol\Type\CompletionItem;
use Lsp\Protocol\Type\CompletionItemKind;
use Lsp\Protocol\Type\CompletionParams;

#[AsCompletionContributor]
final class ClassesCompletionContributor implements CompletionContributor
{
    public function __construct(
        private readonly IndexLookup $indexLookup,
    )
    {
    }

    public function contribute(CompletionContext $context, CompletionConsumer $consumer): void
    {
        foreach ($this->indexLookup->findByKey(ClassIndexer::class) as $key => $value) {
            $consumer(new CompletionItem(
                label: $value->value,
                kind: CompletionItemKind::ClassKind,
                detail: '[class]',
            ));
        }
    }

    public function provide(): array
    {
        return get_defined_functions();
    }

    private function filter(array $functions): array
    {
        $result = [];

        $result = array_splice($functions, 0, 50);
        return $result;
    }
}

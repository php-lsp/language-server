<?php

declare(strict_types=1);

namespace App\Module\Completion;

use App\Core\Contracts\Completion\AsCompletionContributor;
use App\Core\Contracts\Completion\CompletionConsumer;
use App\Core\Contracts\Completion\CompletionContext;
use App\Core\Contracts\Completion\CompletionContributor;
use Lsp\Protocol\Type\CompletionItem;

#[AsCompletionContributor]
final class KeywordsCompletionContributor implements CompletionContributor
{
    public function contribute(CompletionContext $context, CompletionConsumer $consumer): void
    {
        foreach (KeywordDefinitions::ALL as [$category, $keywords]) {
            foreach ($keywords as $keyword) {
                $consumer(new CompletionItem(
                    label: $keyword,
                    kind: KeywordDefinitions::getCompletionKind($category),
                    detail: $category->value,
                    insertText: $keyword . KeywordDefinitions::getCompletionSuffix($category),
                ));
            }
        }
    }
}

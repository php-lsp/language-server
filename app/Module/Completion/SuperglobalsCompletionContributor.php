<?php

namespace App\Module\Completion;

use App\Core\Contracts\Completion\AsCompletionContributor;
use App\Core\Contracts\Completion\CompletionConsumer;
use App\Core\Contracts\Completion\CompletionContext;
use App\Core\Contracts\Completion\CompletionContributor;
use Lsp\Protocol\Type\CompletionItem;
use Lsp\Protocol\Type\CompletionItemKind;

#[AsCompletionContributor]
final class SuperglobalsCompletionContributor implements CompletionContributor
{
    public function contribute(CompletionContext $context, CompletionConsumer $consumer): void
    {
        foreach ($this->provide() as $value) {
            $consumer(new CompletionItem(
                label: $value,
                kind: CompletionItemKind::VariableKind,
                detail: 'PHP global var',
            ));
        }
    }

    public function provide(): array
    {
        return [
            '$GLOBALS',
            '$_SERVER',
            '$_GET',
            '$_POST',
            '$_FILES',
            '$_REQUEST',
            '$_SESSION',
            '$_ENV',
            '$_COOKIE',
        ];
    }
}

<?php

declare(strict_types=1);

namespace App\Module\Completion;

use App\Core\Contracts\Completion\AsCompletionContributor;
use App\Core\Contracts\CompletionConsumer;
use App\Core\Contracts\CompletionContext;
use App\Core\Contracts\CompletionContributor;
use Lsp\Protocol\Type\CompletionItem;
use Lsp\Protocol\Type\CompletionItemKind;
use Lsp\Protocol\Type\CompletionParams;

#[AsCompletionContributor]
final class FunctionCompletionContributor implements CompletionContributor
{
    public function contribute(CompletionContext $context, CompletionConsumer $consumer): void
    {
        ['internal' => $internalFunctions, 'user' => $userFunctions] = $this->provide();
        $internalFunctions = $this->filter($internalFunctions);

        foreach ($internalFunctions as $functionName) {
            $consumer(new CompletionItem(
                label: $functionName,
                kind: CompletionItemKind::FunctionKind,
                detail: '[internal function]',
            ));
        }

        $userFunctions = $this->filter($userFunctions);

        foreach ($userFunctions as $functionName) {
            $consumer(new CompletionItem(
                label: $functionName,
                kind: CompletionItemKind::FunctionKind,
                detail: '[user function]',
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

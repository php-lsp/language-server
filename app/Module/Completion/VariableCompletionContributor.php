<?php

declare(strict_types=1);

namespace App\Module\Completion;

use App\Core\Contracts\Completion\AsCompletionContributor;
use App\Core\Contracts\Completion\CompletionConsumer;
use App\Core\Contracts\Completion\CompletionContext;
use App\Core\Contracts\Completion\CompletionContributor;
use App\Module\PsiFile\Tree;
use Lsp\Protocol\Type\CompletionItem;
use Lsp\Protocol\Type\CompletionItemKind;
use Override;
use PhpParser\Node;
use PhpParser\NodeFinder;

#[AsCompletionContributor]
final class VariableCompletionContributor implements CompletionContributor
{
    #[Override]
    public function contribute(CompletionContext $context, CompletionConsumer $consumer): void
    {
        $element = $context->currentNode();
        if ($element === null) {
            return;
        }

        // Only trigger if current element is a variable
        if (!$element instanceof Node\Expr\Variable) {
            return;
        }

        // Find the enclosing function/method scope
        $scope = Tree::parentOfType($element, Node\Stmt\ClassMethod::class) ?? Tree::parentOfType(
            $element,
            Node\Stmt\Function_::class,
        );

        if ($scope === null) {
            return;
        }

        $seen = [];

        // Add parameters as completions
        foreach ($scope->params as $param) {
            if ($param->var instanceof Node\Expr\Variable && is_string($param->var->name)) {
                $name = $param->var->name;
                if (array_key_exists($name, $seen)) {
                    continue;
                }
                $seen[$name] = true;

                $detail = '';
                if ($param->type !== null) {
                    $detail = Tree::toString($param->type);
                }

                $consumer(new CompletionItem(
                    label: '$' . $name,
                    kind: CompletionItemKind::VariableKind,
                    detail: $detail,
                ));
            }
        }

        // Find all variable assignments in scope
        $finder = new NodeFinder();
        $variables = $finder->findInstanceOf($scope->stmts ?? [], Node\Expr\Variable::class);
        foreach ($variables as $variable) {
            if (!is_string($variable->name)) {
                continue;
            }
            $name = $variable->name;
            if (array_key_exists($name, $seen)) {
                continue;
            }
            $seen[$name] = true;

            $consumer(new CompletionItem(
                label: '$' . $name,
                kind: CompletionItemKind::VariableKind,
                detail: '[variable]',
            ));
        }
    }
}

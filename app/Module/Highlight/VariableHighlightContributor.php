<?php

declare(strict_types=1);

namespace App\Module\Highlight;

use App\Core\Contracts\Highlight\AsDocumentHighlightContributor;
use App\Core\Contracts\Highlight\DocumentHighlightConsumer;
use App\Core\Contracts\Highlight\DocumentHighlightContext;
use App\Core\Contracts\Highlight\DocumentHighlightContributor;
use App\Module\PsiFile\Tree;
use Lsp\Protocol\Type\DocumentHighlight;
use Lsp\Protocol\Type\DocumentHighlightKind;
use PhpParser\Node;
use PhpParser\NodeFinder;

#[AsDocumentHighlightContributor]
final class VariableHighlightContributor implements DocumentHighlightContributor
{
    public function contribute(DocumentHighlightContext $context, DocumentHighlightConsumer $consumer): void
    {
        $file = $context->fileManager->findPsiFile($context->editor, $context->textDocumentIdentifier);
        if ($file === null) {
            return;
        }

        $element = $file->findLastAtPosition($context->position);
        if (!$element instanceof Node\Expr\Variable) {
            return;
        }

        if (!is_string($element->name)) {
            return;
        }

        $variableName = $element->name;

        // Find the enclosing scope
        $scope = Tree::parentOfType($element, Node\Stmt\ClassMethod::class) ?? Tree::parentOfType(
            $element,
            Node\Stmt\Function_::class,
        );

        if ($scope === null) {
            return;
        }

        // Find all variables with the same name in scope
        $finder = new NodeFinder();
        $variables = $finder->findInstanceOf($scope->stmts ?? [], Node\Expr\Variable::class);

        // Also check parameters
        foreach ($scope->params as $param) {
            if (!($param->var instanceof Node\Expr\Variable && $param->var->name === $variableName)) {
                continue;
            }

            $consumer(new DocumentHighlight(
                range: Tree::getRange($param->var, $file),
                kind: DocumentHighlightKind::Write,
            ));
        }

        foreach ($variables as $variable) {
            if (!$variable instanceof Node\Expr\Variable || $variable->name !== $variableName) {
                continue;
            }

            $parent = $variable->getAttribute('parent');
            $kind =
                $parent instanceof Node\Expr\Assign && $parent->var === $variable
                    ? DocumentHighlightKind::Write
                    : DocumentHighlightKind::Read;

            $consumer(new DocumentHighlight(
                range: Tree::getRange($variable, $file),
                kind: $kind,
            ));
        }
    }
}

<?php

declare(strict_types=1);

namespace App\Module\Definition;

use App\Core\Contracts\Definition\AsDefinitionContributor;
use App\Core\Contracts\Definition\DefinitionConsumer;
use App\Core\Contracts\Definition\DefinitionContext;
use App\Core\Contracts\Definition\DefinitionContributor;
use App\Module\PsiFile\InMemoryPsiFileManager;
use App\Module\PsiFile\Tree;
use Lsp\Protocol\Type\Location;
use PhpParser\Node;
use PhpParser\NodeFinder;

#[AsDefinitionContributor]
final class VariableDefinitionContributor implements DefinitionContributor
{
    public function __construct(
        private readonly InMemoryPsiFileManager $fileManager,
    ) {}

    public function contribute(DefinitionContext $context, DefinitionConsumer $consumer): void
    {
        $editor = $context->editor;
        $file = $this->fileManager->findPsiFile($editor, $context->textDocumentIdentifier);
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

        // Find the enclosing function/method
        $scope = Tree::parentOfType($element, Node\Stmt\ClassMethod::class) ?? Tree::parentOfType(
            $element,
            Node\Stmt\Function_::class,
        );

        if ($scope === null) {
            return;
        }

        // Check parameters first
        foreach ($scope->params as $param) {
            if (!$param->var instanceof Node\Expr\Variable || $param->var->name !== $variableName) {
                continue;
            }

            $consumer(new Location(
                uri: $context->textDocumentIdentifier->uri,
                range: Tree::getRange($param, $file),
            ));

            return;
        }

        // Find first assignment in function body
        $finder = new NodeFinder();
        $assignments = $finder->findInstanceOf($scope->stmts ?? [], Node\Expr\Assign::class);
        foreach ($assignments as $assign) {
            if (!$assign->var instanceof Node\Expr\Variable || $assign->var->name !== $variableName) {
                continue;
            }

            $consumer(new Location(
                uri: $context->textDocumentIdentifier->uri,
                range: Tree::getRange($assign->var, $file),
            ));

            return;
        }
    }
}

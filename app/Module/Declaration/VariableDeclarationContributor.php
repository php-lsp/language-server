<?php

declare(strict_types=1);

namespace App\Module\Declaration;

use App\Core\Contracts\Declaration\AsDeclarationContributor;
use App\Core\Contracts\Declaration\DeclarationConsumer;
use App\Core\Contracts\Declaration\DeclarationContext;
use App\Core\Contracts\Declaration\DeclarationContributor;
use App\Module\PsiFile\InMemoryPsiFileManager;
use App\Module\PsiFile\Tree;
use Lsp\Protocol\Type\Location;
use PhpParser\Node;
use PhpParser\NodeFinder;

#[AsDeclarationContributor]
final class VariableDeclarationContributor implements DeclarationContributor
{
    public function __construct(
        private readonly InMemoryPsiFileManager $fileManager,
    ) {}

    public function contribute(DeclarationContext $context, DeclarationConsumer $consumer): void
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

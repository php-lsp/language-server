<?php

declare(strict_types=1);

namespace App\Module\References;

use App\Core\Contracts\References\AsReferenceContributor;
use App\Core\Contracts\References\ReferenceConsumer;
use App\Core\Contracts\References\ReferenceContext;
use App\Core\Contracts\References\ReferenceContributor;
use App\Module\PsiFile\InMemoryPsiFileManager;
use App\Module\PsiFile\Tree;
use Lsp\Protocol\Type\Location;
use Lsp\Protocol\Type\Position;
use Lsp\Protocol\Type\Range;
use Override;
use PhpParser\Node;
use PhpParser\NodeFinder;

#[AsReferenceContributor]
final class VariableReferenceContributor implements ReferenceContributor
{
    public function __construct(
        private readonly InMemoryPsiFileManager $fileManager,
    ) {}

    #[Override]
    public function contribute(ReferenceContext $context, ReferenceConsumer $consumer): void
    {
        $file = $this->fileManager->findPsiFile($context->editor, $context->textDocumentIdentifier);
        if ($file === null) {
            return;
        }

        $element = $file->findLastAtPosition($context->position);

        $variableName = $this->resolveVariableName($element);
        if ($variableName === null) {
            return;
        }

        $scope = $this->findScope($element);

        $finder = new NodeFinder();
        $searchNodes = $scope instanceof Node ? [$scope] : $file->getChildren();

        $variables = $finder->findInstanceOf($searchNodes, Node\Expr\Variable::class);

        foreach ($variables as $variable) {
            if (!is_string($variable->name) || $variable->name !== $variableName) {
                continue;
            }

            /** @var array{int<0, 2147483647>, int<0, 2147483647>} $lineCol */
            $lineCol = Tree::toLineColumn(
                $file->getDocument(),
                $variable->getStartFilePos(),
            );
            $position = new Position($lineCol[0], $lineCol[1]);

            $consumer(new Location(
                uri: $context->textDocumentIdentifier->uri,
                range: new Range($position, $position),
            ));
        }
    }

    private function resolveVariableName(?Node $element): ?string
    {
        if ($element instanceof Node\Expr\Variable && is_string($element->name)) {
            return $element->name;
        }

        return null;
    }

    private function findScope(?Node $element): ?Node
    {
        if ($element === null) {
            return null;
        }

        $scope = Tree::parentOfType($element, Node\Stmt\Function_::class);
        if ($scope !== null) {
            return $scope;
        }

        $scope = Tree::parentOfType($element, Node\Stmt\ClassMethod::class);
        if ($scope !== null) {
            return $scope;
        }

        $scope = Tree::parentOfType($element, Node\Expr\Closure::class);
        if ($scope !== null) {
            return $scope;
        }

        return Tree::parentOfType($element, Node\Expr\ArrowFunction::class);
    }
}

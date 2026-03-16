<?php

declare(strict_types=1);

namespace App\Module\FoldingRange;

use App\Core\Contracts\FoldingRange\AsFoldingRangeContributor;
use App\Core\Contracts\FoldingRange\FoldingRangeConsumer;
use App\Core\Contracts\FoldingRange\FoldingRangeContext;
use App\Core\Contracts\FoldingRange\FoldingRangeContributor;
use App\Module\PsiFile\Tree;
use Lsp\Protocol\Type\FoldingRange;
use Lsp\Protocol\Type\FoldingRangeKind;
use Override;
use PhpParser\NodeFinder;

/**
 * Emits folding ranges for AST nodes (classes, methods, functions, control structures, arrays).
 */
#[AsFoldingRangeContributor]
final class AstFoldingRangeContributor implements FoldingRangeContributor
{
    #[Override]
    public function contribute(FoldingRangeContext $context, FoldingRangeConsumer $consumer): void
    {
        $file = $context->fileManager->findPsiFile($context->editor, $context->textDocumentIdentifier);
        if ($file === null) {
            return;
        }

        $finder = new NodeFinder();
        $nodes = $finder->find(
            $file->ast->children,
            FoldableNodeMatcher::isFoldable(...),
        );

        foreach ($nodes as $node) {
            /** @var int<0, 2147483647> $startLine */
            $startLine = Tree::nodeStartLine($node);
            /** @var int<0, 2147483647> $endLine */
            $endLine = Tree::nodeEndLine($node);

            if ($startLine >= $endLine) {
                continue;
            }

            $consumer(new FoldingRange(
                startLine: $startLine,
                endLine: $endLine,
                kind: FoldingRangeKind::Region,
            ));
        }
    }
}

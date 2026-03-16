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
use PhpParser\Node;
use PhpParser\Node\Stmt\GroupUse;
use PhpParser\Node\Stmt\Namespace_;
use PhpParser\Node\Stmt\Use_;

/**
 * Emits folding ranges for consecutive use/import statement blocks.
 */
#[AsFoldingRangeContributor]
final class UseBlockFoldingRangeContributor implements FoldingRangeContributor
{
    #[Override]
    public function contribute(FoldingRangeContext $context, FoldingRangeConsumer $consumer): void
    {
        $file = $context->fileManager->findPsiFile($context->editor, $context->textDocumentIdentifier);
        if ($file === null) {
            return;
        }

        $stmts = $file->ast->children;

        // Handle namespace-wrapped statements
        foreach ($stmts as $stmt) {
            if (!$stmt instanceof Namespace_) {
                continue;
            }

            $this->processUseStatements($stmt->stmts, $consumer);
        }

        // Handle top-level use statements
        $this->processUseStatements($stmts, $consumer);
    }

    /**
     * @param array<Node> $stmts
     */
    private function processUseStatements(array $stmts, FoldingRangeConsumer $consumer): void
    {
        $useNodes = [];

        foreach ($stmts as $stmt) {
            if ($stmt instanceof Use_ || $stmt instanceof GroupUse) {
                $useNodes[] = $stmt;

                continue;
            }

            $this->emitUseBlock($useNodes, $consumer);
            $useNodes = [];
        }

        $this->emitUseBlock($useNodes, $consumer);
    }

    /**
     * @param array<Node> $useNodes
     */
    private function emitUseBlock(array $useNodes, FoldingRangeConsumer $consumer): void
    {
        if (\count($useNodes) < 2) {
            return;
        }

        $first = $useNodes[0];
        $last = $useNodes[\count($useNodes) - 1];

        /** @var int<0, 2147483647> $startLine */
        $startLine = Tree::nodeStartLine($first);
        /** @var int<0, 2147483647> $endLine */
        $endLine = Tree::nodeEndLine($last);

        if ($startLine >= $endLine) {
            return;
        }

        $consumer(new FoldingRange(
            startLine: $startLine,
            endLine: $endLine,
            kind: FoldingRangeKind::Imports,
        ));
    }
}

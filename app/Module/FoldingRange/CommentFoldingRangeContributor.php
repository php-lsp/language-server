<?php

declare(strict_types=1);

namespace App\Module\FoldingRange;

use App\Core\Contracts\FoldingRange\AsFoldingRangeContributor;
use App\Core\Contracts\FoldingRange\FoldingRangeConsumer;
use App\Core\Contracts\FoldingRange\FoldingRangeContext;
use App\Core\Contracts\FoldingRange\FoldingRangeContributor;
use Lsp\Protocol\Type\FoldingRange;
use Lsp\Protocol\Type\FoldingRangeKind;
use Override;
use PhpParser\NodeFinder;

/**
 * Emits folding ranges for multi-line comments and docblocks.
 */
#[AsFoldingRangeContributor]
final class CommentFoldingRangeContributor implements FoldingRangeContributor
{
    #[Override]
    public function contribute(FoldingRangeContext $context, FoldingRangeConsumer $consumer): void
    {
        $file = $context->fileManager->findPsiFile($context->editor, $context->textDocumentIdentifier);
        if ($file === null) {
            return;
        }

        /** @var array<string, true> */
        $seen = [];

        $finder = new NodeFinder();
        $allNodes = $finder->find($file->ast->children, static fn(): bool => true);

        foreach ($allNodes as $node) {
            foreach ($node->getComments() as $comment) {
                $key = $comment->getStartLine() . ':' . $comment->getStartFilePos();
                if (\array_key_exists($key, $seen)) {
                    continue;
                }
                $seen[$key] = true;

                /** @var int<0, 2147483647> $startLine */
                $startLine = $comment->getStartLine() - 1;
                /** @var int<0, 2147483647> $endLine */
                $endLine = $comment->getEndLine() - 1;

                if ($startLine >= $endLine) {
                    continue;
                }

                $consumer(new FoldingRange(
                    startLine: $startLine,
                    endLine: $endLine,
                    kind: FoldingRangeKind::Comment,
                ));
            }
        }
    }
}

<?php

declare(strict_types=1);

namespace App\Module\SelectionRange;

use App\Core\Contracts\SelectionRange\AsSelectionRangeContributor;
use App\Core\Contracts\SelectionRange\SelectionRangeConsumer;
use App\Core\Contracts\SelectionRange\SelectionRangeContext;
use App\Core\Contracts\SelectionRange\SelectionRangeContributor;
use App\Module\PsiFile\InMemoryPsiFileManager;
use App\Module\PsiFile\PHPPsiFile;
use App\Module\PsiFile\Tree;
use Lsp\Protocol\Type\Position;
use Lsp\Protocol\Type\Range;
use Lsp\Protocol\Type\SelectionRange;
use Override;
use PhpParser\Node;

#[AsSelectionRangeContributor]
final class AstSelectionRangeContributor implements SelectionRangeContributor
{
    public function __construct(
        private readonly InMemoryPsiFileManager $fileManager,
    ) {}

    #[Override]
    public function contribute(SelectionRangeContext $context, SelectionRangeConsumer $consumer): void
    {
        $psiFile = $this->fileManager->findPsiFile($context->editor, $context->textDocumentIdentifier);
        if ($psiFile === null) {
            return;
        }

        foreach ($context->positions as $index => $position) {
            $selectionRange = $this->buildSelectionRange($psiFile, $position);
            if ($selectionRange !== null) {
                $consumer($index, $selectionRange);
            }
        }
    }

    private function buildSelectionRange(PHPPsiFile $psiFile, Position $position): ?SelectionRange
    {
        $nodes = $psiFile->findAtPosition($position);
        if ($nodes === []) {
            return null;
        }

        // Nodes are returned from root to leaf by NodeFinder.
        // We build the chain from outermost (root) to innermost (leaf),
        // where each SelectionRange's parent is the next outer range.
        // Filter out nodes with identical ranges to avoid redundant nesting.
        /** @var list<Range> $ranges */
        $ranges = [];
        foreach ($nodes as $node) {
            $range = $this->nodeToRange($node, $psiFile);
            if ($range !== null) {
                $ranges[] = $range;
            }
        }

        if ($ranges === []) {
            return null;
        }

        // Deduplicate consecutive identical ranges
        /** @var list<Range> $uniqueRanges */
        $uniqueRanges = [$ranges[0]];
        for ($i = 1, $count = count($ranges); $i < $count; $i++) {
            $prev = $uniqueRanges[count($uniqueRanges) - 1];
            $curr = $ranges[$i];
            if (!$this->rangesEqual($prev, $curr)) {
                $uniqueRanges[] = $curr;
            }
        }

        // Build the chain from outermost to innermost.
        // The outermost node has no parent.
        $selectionRange = null;
        foreach ($uniqueRanges as $range) {
            $selectionRange = new SelectionRange(
                range: $range,
                parent: $selectionRange,
            );
        }

        return $selectionRange;
    }

    private function nodeToRange(Node $node, PHPPsiFile $psiFile): ?Range
    {
        $startFilePos = $node->getStartFilePos();
        $endFilePos = $node->getEndFilePos();

        if ($startFilePos < 0 || $endFilePos < 0) {
            return null;
        }

        $document = $psiFile->ast->document;
        [$startLine, $startCol] = Tree::toLineColumn($document, $startFilePos);
        [$endLine, $endCol] = Tree::toLineColumn($document, $endFilePos + 1);

        /** @var int<0, 2147483647> $startLine */
        /** @var int<0, 2147483647> $startCol */
        /** @var int<0, 2147483647> $endLine */
        /** @var int<0, 2147483647> $endCol */

        return new Range(
            start: new Position($startLine, $startCol),
            end: new Position($endLine, $endCol),
        );
    }

    private function rangesEqual(Range $a, Range $b): bool
    {
        return (
            $a->start->line === $b->start->line
            && $a->start->character === $b->start->character
            && $a->end->line === $b->end->line
            && $a->end->character === $b->end->character
        );
    }
}

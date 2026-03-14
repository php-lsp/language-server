<?php

declare(strict_types=1);

namespace App\Module\PsiFile;

use Lsp\Extension\DocumentManager\Editor\Document\Document;
use Lsp\Protocol\Type\Position;
use PhpParser\Node;
use PhpParser\NodeFinder;

class PHPPsiFile
{
    public function __construct(
        public readonly SourceFileRoot $ast,
    ) {}

    /**
     * @return array<Node>
     */
    public function findAtPosition(Position|int $position): array
    {
        if (is_int($position)) {
            $visitor = new NodeFinder();

            return $visitor->find($this->ast->children, function (Node $node) use ($position, &$line) {
                return $node->getStartFilePos() <= $position && $position <= $node->getEndFilePos();
            });
        }
        if ($position instanceof Position) {
            $line = $position->line + 1;

            $visitor = new NodeFinder();

            return $visitor->find($this->ast->children, function (Node $node) use ($position, &$line) {
                if (
                    $node->getStartLine() <= $line && $line <= $node->getEndLine()
                ) {
                    //                $length = $node->getEndFilePos() - $node->getStartFilePos();
                    $startColumn = $this->toColumn($this->ast->document, $node->getStartFilePos());
                    $endColumn = $this->toColumn($this->ast->document, $node->getEndFilePos());

                    $result = $startColumn <= $position->character && $position->character <= $endColumn;

                    return $result;
                }

                return false;
            });
        }

        return [];
    }

    public function findLastAtPosition(Position $position): ?Node
    {
        $nodes = $this->findAtPosition($position);

        return end($nodes) ?: null;
    }

    private function toColumn(Document $document, int $pos): int
    {
        $text = $document->getContents();
        if ($pos > strlen($text)) {
            throw new \RuntimeException('Invalid position information');
        }

        $lineStartPos = strrpos($text, "\n", $pos - strlen($text));
        if (false === $lineStartPos) {
            $lineStartPos = -1;
        }

        return $pos - $lineStartPos;
    }
}

<?php

declare(strict_types=1);

namespace App\Module\PsiFile;

use App\Core\Contracts\PsiFile\PsiFileInterface;
use Lsp\Extension\DocumentManager\Editor\Document\Document;
use Lsp\Protocol\Type\Position;
use Override;
use PhpParser\Node;
use PhpParser\NodeFinder;

class PHPPsiFile implements PsiFileInterface
{
    public function __construct(
        public readonly SourceFileRoot $ast,
    ) {}

    /**
     * @return array<Node>
     */
    #[Override]
    public function findAtPosition(Position|int $position): array
    {
        if (is_int($position)) {
            $visitor = new NodeFinder();

            return $visitor->find(
                $this->ast->children,
                static fn(Node $node) => $node->getStartFilePos() <= $position && $position <= $node->getEndFilePos(),
            );
        }
        if ($position instanceof Position) {
            $line = Tree::toParserLine($position->line);

            $visitor = new NodeFinder();

            return $visitor->find($this->ast->children, function (Node $node) use ($position, $line) {
                if ($node->getStartLine() <= $line && $line <= $node->getEndLine()) {
                    $startColumn = $this->toColumn($this->ast->document, $node->getStartFilePos());
                    $endColumn = $this->toColumn($this->ast->document, $node->getEndFilePos());

                    return $startColumn <= $position->character && $position->character <= $endColumn;
                }

                return false;
            });
        }

        return [];
    }

    #[Override]
    public function findLastAtPosition(Position $position): ?Node
    {
        $nodes = $this->findAtPosition($position);

        $last = end($nodes);

        return $last !== false ? $last : null;
    }

    private function toColumn(Document $document, int $pos): int
    {
        $text = $document->getContents();
        if ($pos > strlen($text)) {
            throw new \RuntimeException('Invalid position information');
        }

        $needle = "\n";
        $lineStartPos = strrpos($text, $needle, $pos - strlen($text));
        if (false === $lineStartPos) {
            $lineStartPos = -1;
        }

        return $pos - $lineStartPos;
    }
}

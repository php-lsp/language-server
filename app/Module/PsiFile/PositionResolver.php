<?php

declare(strict_types=1);

namespace App\Module\PsiFile;

use App\Module\Document\DocumentIdentifierFactoryInterface;
use Lsp\Extension\DocumentManager\Editor\EditorInterface;
use Lsp\Protocol\Type\Position;
use Lsp\Protocol\Type\Range;

final class PositionResolver
{
    public function __construct(
        private readonly InMemoryPsiFileManager $fileManager,
        private readonly DocumentIdentifierFactoryInterface $documentIdentifierFactory,
    ) {}

    public function resolveRange(string $uri, int $startPosition, EditorInterface $editor): Range
    {
        $textDocumentIdentifier = $this->documentIdentifierFactory->create($uri);
        $source = $this->fileManager->findPsiFile($editor, $textDocumentIdentifier);
        if ($source !== null) {
            [$line, $column] = Tree::toLineColumn($source->ast->document, $startPosition);
            $position = new Position($line, $column);

            return new Range($position, $position);
        }

        $zeroPosition = new Position(0, 0);

        return new Range($zeroPosition, $zeroPosition);
    }
}

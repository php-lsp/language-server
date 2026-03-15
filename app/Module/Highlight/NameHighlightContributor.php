<?php

declare(strict_types=1);

namespace App\Module\Highlight;

use App\Core\Contracts\Highlight\AsDocumentHighlightContributor;
use App\Core\Contracts\Highlight\DocumentHighlightConsumer;
use App\Core\Contracts\Highlight\DocumentHighlightContext;
use App\Core\Contracts\Highlight\DocumentHighlightContributor;
use App\Module\PsiFile\Tree;
use Lsp\Protocol\Type\DocumentHighlight;
use Lsp\Protocol\Type\DocumentHighlightKind;
use PhpParser\Node;
use PhpParser\NodeFinder;

#[AsDocumentHighlightContributor]
final class NameHighlightContributor implements DocumentHighlightContributor
{
    public function contribute(DocumentHighlightContext $context, DocumentHighlightConsumer $consumer): void
    {
        $file = $context->fileManager->findPsiFile($context->editor, $context->textDocumentIdentifier);
        if ($file === null) {
            return;
        }

        $element = $file->findLastAtPosition($context->position);
        if (!$element instanceof Node\Name\FullyQualified) {
            return;
        }

        $targetName = $element->toString();

        // Find all FullyQualified names in the file with the same string
        $finder = new NodeFinder();
        $allNames = $finder->findInstanceOf($file->ast->children, Node\Name\FullyQualified::class);

        foreach ($allNames as $name) {
            if ($name->toString() !== $targetName) {
                continue;
            }

            $consumer(new DocumentHighlight(
                range: Tree::getRange($name, $file),
                kind: DocumentHighlightKind::Text,
            ));
        }
    }
}

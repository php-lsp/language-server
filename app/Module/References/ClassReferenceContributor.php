<?php

declare(strict_types=1);

namespace App\Module\References;

use App\Core\Contracts\References\AsReferenceContributor;
use App\Core\Contracts\References\ReferenceConsumer;
use App\Core\Contracts\References\ReferenceContext;
use App\Core\Contracts\References\ReferenceContributor;
use App\Module\Indexing\Indexer\ClassUsageIndexer;
use App\Module\Indexing\IndexLookup;
use App\Module\PsiFile\InMemoryPsiFileManager;
use App\Module\PsiFile\Tree;
use Lsp\Protocol\Type\Location;
use Lsp\Protocol\Type\Position;
use Lsp\Protocol\Type\Range;
use Lsp\Protocol\Type\TextDocumentIdentifier;
use PhpParser\Node;

#[AsReferenceContributor]
final class ClassReferenceContributor implements ReferenceContributor
{
    public function __construct(
        private readonly IndexLookup $indexLookup,
        private readonly InMemoryPsiFileManager $fileManager,
    ) {}

    public function contribute(ReferenceContext $context, ReferenceConsumer $consumer): void
    {
        $file = $this->fileManager->findPsiFile($context->editor, $context->textDocumentIdentifier);
        if ($file === null) {
            return;
        }

        $element = $file->findLastAtPosition($context->position);

        $className = $this->resolveClassName($element);
        if ($className === null) {
            return;
        }

        foreach ($this->indexLookup->findByKey(ClassUsageIndexer::class) as $entry) {
            /** @var array{string, int} $value */
            $value = $entry->value;
            if ($value[0] !== $className) {
                continue;
            }

            /** @var non-empty-string $uri */
            $uri = $entry->uri;
            $source = $this->fileManager->findPsiFile(
                $context->editor,
                new TextDocumentIdentifier($uri),
            );
            if ($source === null) {
                continue;
            }

            /** @var array{int<0, 2147483647>, int<0, 2147483647>} $lineCol */
            $lineCol = Tree::toLineColumn($source->ast->document, $value[1]);
            $position = new Position($lineCol[0], $lineCol[1]);

            $consumer(new Location(
                uri: $uri,
                range: new Range($position, $position),
            ));
        }
    }

    private function resolveClassName(?Node $element): ?string
    {
        if ($element === null) {
            return null;
        }

        if ($element instanceof Node\Name\FullyQualified) {
            $parent = $element->getAttribute('parent');
            if ($parent instanceof Node\Expr\FuncCall) {
                return null;
            }

            return $element->toString();
        }

        if ($element instanceof Node\Identifier) {
            $parent = $element->getAttribute('parent');
            if ($parent instanceof Node\Stmt\Class_) {
                return $parent->namespacedName?->toString() ?? $element->toString();
            }
        }

        return null;
    }
}

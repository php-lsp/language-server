<?php

declare(strict_types=1);

namespace App\Module\References;

use App\Core\Contracts\References\AsReferenceContributor;
use App\Core\Contracts\References\ReferenceConsumer;
use App\Core\Contracts\References\ReferenceContext;
use App\Core\Contracts\References\ReferenceContributor;
use App\Module\Indexing\Indexer\ClassConstantUsageIndexer;
use App\Module\Indexing\IndexLookup;
use App\Module\PsiFile\InMemoryPsiFileManager;
use App\Module\PsiFile\Tree;
use Lsp\Protocol\Type\Location;
use Lsp\Protocol\Type\Position;
use Lsp\Protocol\Type\Range;
use Lsp\Protocol\Type\TextDocumentIdentifier;
use Override;
use PhpParser\Node;

#[AsReferenceContributor]
final class ClassConstantReferenceContributor implements ReferenceContributor
{
    public function __construct(
        private readonly IndexLookup $indexLookup,
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

        $resolved = $this->resolveConstant($element);
        if ($resolved === null) {
            return;
        }

        [$targetClass, $targetConst] = $resolved;

        foreach ($this->indexLookup->findByKey(ClassConstantUsageIndexer::class) as $entry) {
            /** @var array{string, string, int} $value */
            $value = $entry->value;
            if ($value[0] !== $targetClass || $value[1] !== $targetConst) {
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
            $lineCol = Tree::toLineColumn($source->ast->document, $value[2]);
            $position = new Position($lineCol[0], $lineCol[1]);

            $consumer(new Location(
                uri: $uri,
                range: new Range($position, $position),
            ));
        }
    }

    /**
     * @return array{string, string}|null
     */
    private function resolveConstant(?Node $element): ?array
    {
        if ($element === null) {
            return null;
        }

        if ($element instanceof Node\Identifier) {
            $parent = $element->getAttribute('parent');
            if ($parent instanceof Node\Expr\ClassConstFetch && $parent->class instanceof Node\Name) {
                return [$parent->class->toString(), $element->toString()];
            }
            if ($parent instanceof Node\Const_) {
                $classConst = $parent->getAttribute('parent');
                if ($classConst instanceof Node\Stmt\ClassConst) {
                    $class = $classConst->getAttribute('parent');
                    if ($class instanceof Node\Stmt\Class_) {
                        $className = $class->namespacedName?->toString() ?? $class->name?->toString();
                        if ($className !== null) {
                            return [$className, $element->toString()];
                        }
                    }
                }
            }
        }

        return null;
    }
}

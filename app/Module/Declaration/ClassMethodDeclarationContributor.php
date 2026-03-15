<?php

declare(strict_types=1);

namespace App\Module\Declaration;

use App\Core\Contracts\Declaration\AsDeclarationContributor;
use App\Core\Contracts\Declaration\DeclarationConsumer;
use App\Core\Contracts\Declaration\DeclarationContext;
use App\Core\Contracts\Declaration\DeclarationContributor;
use App\Module\Indexing\Data\MethodData;
use App\Module\Indexing\Indexer\ClassMethodIndexer;
use App\Module\Indexing\IndexLookup;
use App\Module\PsiFile\InMemoryPsiFileManager;
use App\Module\PsiFile\Tree;
use Lsp\Protocol\Type\Location;
use Lsp\Protocol\Type\Position;
use Lsp\Protocol\Type\Range;
use PhpParser\Node;

#[AsDeclarationContributor]
final class ClassMethodDeclarationContributor implements DeclarationContributor
{
    public function __construct(
        private readonly IndexLookup $indexLookup,
        private readonly InMemoryPsiFileManager $fileManager,
    ) {}

    public function contribute(DeclarationContext $context, DeclarationConsumer $consumer): void
    {
        $editor = $context->editor;
        $file = $this->fileManager->findPsiFile($editor, $context->textDocumentIdentifier);
        if ($file === null) {
            return;
        }

        $element = $file->findLastAtPosition($context->position);
        if (!$element instanceof Node\Name\FullyQualified) {
            return;
        }

        $node = Tree::parentOfType($element, Node\Expr\StaticCall::class);
        if ($node === null) {
            return;
        }

        $className = $node->class->toString();
        $methodName = $node->name->toString();

        $zeroPosition = new Position(0, 0);
        $startRange = new Range($zeroPosition, $zeroPosition);

        foreach ($this->indexLookup->findByKey(ClassMethodIndexer::class) as $value) {
            if ($value->key !== $className) {
                continue;
            }

            /** @var list<MethodData> $methods */
            $methods = $value->value;

            foreach ($methods as $method) {
                if ($method->name !== $methodName) {
                    continue;
                }

                $consumer(new Location(
                    uri: $value->uri,
                    range: $startRange,
                ));
            }
        }
    }
}

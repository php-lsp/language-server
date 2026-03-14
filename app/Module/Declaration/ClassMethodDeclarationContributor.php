<?php

declare(strict_types=1);

namespace App\Module\Declaration;

use App\Core\Contracts\Declaration\AsDeclarationContributor;
use App\Core\Contracts\Declaration\DeclarationConsumer;
use App\Core\Contracts\Declaration\DeclarationContext;
use App\Core\Contracts\Declaration\DeclarationContributor;
use App\Module\Document\DocumentIdentifierFactoryInterface;
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
        private readonly DocumentIdentifierFactoryInterface $documentIdentifierFactory,
    ) {}

    public function contribute(DeclarationContext $context, DeclarationConsumer $consumer): void
    {
        $editor = $context->editor;
        $file = $this->fileManager->findPsiFile($editor, $context->textDocumentIdentifier);
        if ($file === null) {
            //            dump('file is null', $context->textDocumentIdentifier);
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

        foreach ($this->indexLookup->findByKey(ClassMethodIndexer::class) as $value) {
            if ($value->value->className !== $className) {
                continue;
            }
            if ($value->value->name !== $methodName) {
                continue;
            }

            $textDocumentIdentifier = $this->documentIdentifierFactory->create($value->uri);
            $source = $this->fileManager->findPsiFile($context->editor, $textDocumentIdentifier);

            if ($source !== null) {
                [$line, $column] = Tree::toLineColumn($source->ast->document, $value->value->startPosition);
                $exactPosition = new Position($line, $column);
                $range = new Range($exactPosition, $exactPosition);
            } else {
                $zeroPosition = new Position(0, 0);
                $range = new Range($zeroPosition, $zeroPosition);
            }

            $consumer(new Location(
                uri: $value->uri,
                range: $range,
            ));
        }
    }
}

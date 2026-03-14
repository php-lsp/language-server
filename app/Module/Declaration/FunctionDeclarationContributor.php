<?php

declare(strict_types=1);

namespace App\Module\Declaration;

use App\Core\Contracts\Declaration\AsDeclarationContributor;
use App\Core\Contracts\Declaration\DeclarationConsumer;
use App\Core\Contracts\Declaration\DeclarationContext;
use App\Core\Contracts\Declaration\DeclarationContributor;
use App\Module\Document\DocumentIdentifierFactoryInterface;
use App\Module\Indexing\Indexer\FunctionIndexer;
use App\Module\Indexing\IndexLookup;
use App\Module\PsiFile\InMemoryPsiFileManager;
use App\Module\PsiFile\Tree;
use Lsp\Protocol\Type\Location;
use Lsp\Protocol\Type\Position;
use Lsp\Protocol\Type\Range;
use PhpParser\Node;

#[AsDeclarationContributor]
final class FunctionDeclarationContributor implements DeclarationContributor
{
    public function __construct(
        private readonly IndexLookup $indexLookup,
        private readonly InMemoryPsiFileManager $fileManager,
        private readonly DocumentIdentifierFactoryInterface $documentIdentifierFactory,
    ) {}

    public function contribute(DeclarationContext $context, DeclarationConsumer $consumer): void
    {
        $editor = $context->editor;
        $document = $editor->findByUriString($context->textDocumentIdentifier->uri);
        if ($document === null) {
            //            dump('document is null', $context->textDocumentIdentifier);
            return;
        }

        $file = $this->fileManager->findPsiFile($editor, $context->textDocumentIdentifier);
        if ($file === null) {
            //            dump('file is null', $context->textDocumentIdentifier);
            return;
        }

        $element = $file->findLastAtPosition($context->position);
        if (!$element instanceof Node\Name\FullyQualified) {
            return;
        }

        $node = Tree::parentOfType($element, Node\Expr\FuncCall::class);
        if ($node === null) {
            return;
        }

        $functionName = $node->name->toString();

        foreach ($this->indexLookup->findByKey(FunctionIndexer::class) as $value) {
            if ($value->value->fqn !== $functionName) {
                continue;
            }
            $textDocumentIdentifier = $this->documentIdentifierFactory->create($value->uri);
            $source = $this->fileManager->findPsiFile($context->editor, $textDocumentIdentifier);
            $position = $value->value->startPosition;

            [$line, $column] = Tree::toLineColumn($source->ast->document, $position);

            $exactPosition = new Position($line, $column);
            $startRange = new Range($exactPosition, $exactPosition);

            $consumer(new Location(
                uri: $value->uri,
                range: $startRange,
            ));
        }
    }
}

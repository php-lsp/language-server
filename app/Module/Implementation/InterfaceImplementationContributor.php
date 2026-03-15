<?php

declare(strict_types=1);

namespace App\Module\Implementation;

use App\Core\Contracts\Implementation\AsImplementationContributor;
use App\Core\Contracts\Implementation\ImplementationConsumer;
use App\Core\Contracts\Implementation\ImplementationContext;
use App\Core\Contracts\Implementation\ImplementationContributor;
use App\Module\Document\DocumentIdentifierFactoryInterface;
use App\Module\Indexing\Data\ClassData;
use App\Module\Indexing\Data\InheritanceData;
use App\Module\Indexing\Indexer\ClassIndexer;
use App\Module\Indexing\Indexer\InheritanceIndexer;
use App\Module\Indexing\IndexLookup;
use App\Module\PsiFile\InMemoryPsiFileManager;
use App\Module\PsiFile\Tree;
use Lsp\Extension\DocumentManager\Editor\EditorInterface;
use Lsp\Protocol\Type\Location;
use Lsp\Protocol\Type\Position;
use Lsp\Protocol\Type\Range;
use Override;
use PhpParser\Node;

#[AsImplementationContributor]
final class InterfaceImplementationContributor implements ImplementationContributor
{
    public function __construct(
        private readonly IndexLookup $indexLookup,
        private readonly InMemoryPsiFileManager $fileManager,
        private readonly DocumentIdentifierFactoryInterface $documentIdentifierFactory,
    ) {}

    #[Override]
    public function contribute(ImplementationContext $context, ImplementationConsumer $consumer): void
    {
        $file = $this->fileManager->findPsiFile($context->editor, $context->textDocumentIdentifier);
        if ($file === null) {
            return;
        }

        $element = $file->findLastAtPosition($context->position);
        if (!$element instanceof Node\Name\FullyQualified) {
            return;
        }

        $targetFqn = $element->toString();

        foreach ($this->indexLookup->findByKey(InheritanceIndexer::class) as $entry) {
            /** @var InheritanceData $data */
            $data = $entry->value;

            if (!in_array($targetFqn, $data->parents, true)) {
                continue;
            }

            $this->findDeclarationLocation($data->fqn, $context->editor, $consumer);
        }
    }

    private function findDeclarationLocation(
        string $fqn,
        EditorInterface $editor,
        ImplementationConsumer $consumer,
    ): void {
        foreach ($this->indexLookup->findByKey(ClassIndexer::class) as $entry) {
            /** @var ClassData $data */
            $data = $entry->value;
            if ($data->fqn === $fqn) {
                $range = $this->resolveRange($entry->uri, $data->startPosition, $editor);
                $consumer(new Location(uri: $entry->uri, range: $range));

                return;
            }
        }
    }

    private function resolveRange(string $uri, int $startPosition, EditorInterface $editor): Range
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

<?php

declare(strict_types=1);

namespace App\Module\Implementation;

use App\Core\Contracts\Implementation\AsImplementationContributor;
use App\Core\Contracts\Implementation\ImplementationConsumer;
use App\Core\Contracts\Implementation\ImplementationContext;
use App\Core\Contracts\Implementation\ImplementationContributor;
use App\Module\Indexing\Data\ClassData;
use App\Module\Indexing\Data\InheritanceData;
use App\Module\Indexing\Indexer\ClassIndexer;
use App\Module\Indexing\Indexer\InheritanceIndexer;
use App\Module\Indexing\IndexLookup;
use App\Module\PsiFile\InMemoryPsiFileManager;
use App\Module\PsiFile\PositionResolver;
use Lsp\Extension\DocumentManager\Editor\EditorInterface;
use Lsp\Protocol\Type\Location;
use Override;
use PhpParser\Node;

#[AsImplementationContributor]
final class InterfaceImplementationContributor implements ImplementationContributor
{
    public function __construct(
        private readonly IndexLookup $indexLookup,
        private readonly InMemoryPsiFileManager $fileManager,
        private readonly PositionResolver $positionResolver,
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
                $range = $this->positionResolver->resolveRange($entry->uri, $data->startPosition, $editor);
                $consumer(new Location(uri: $entry->uri, range: $range));

                return;
            }
        }
    }
}

<?php

declare(strict_types=1);

namespace App\Controller\TextDocument;

use App\Core\Contracts\References\ReferenceConsumer;
use App\Core\Contracts\References\ReferenceContext;
use App\Core\Contracts\References\ReferenceContributor;
use Lsp\Extension\DocumentManager\Editor\EditorInterface;
use Lsp\Kernel\Attribute\AsController;
use Lsp\Protocol\Type\RenameParams;
use Lsp\Protocol\Type\TextEdit;
use Lsp\Protocol\Type\WorkspaceEdit;
use Lsp\Router\Attribute\Route;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;

#[AsController, Route('textDocument/rename')]
final class RenameController
{
    /**
     * @var list<ReferenceContributor>
     */
    private array $contributors;

    public function __construct(
        #[AutowireIterator('lsp.referenceContributors')]
        iterable $contributors,
    ) {
        $this->contributors = iterator_to_array($contributors);
    }

    public function __invoke(EditorInterface $editor, RenameParams $params): ?WorkspaceEdit
    {
        $context = new ReferenceContext($params->textDocument, $params->position, $editor);
        $consumer = new ReferenceConsumer();

        foreach ($this->contributors as $contributor) {
            $contributor->contribute($context, $consumer);
        }

        if ($consumer->results === []) {
            return null;
        }

        /** @var array<non-empty-string, list<TextEdit>> $changes */
        $changes = [];

        foreach ($consumer->results as $location) {
            $changes[$location->uri][] = new TextEdit(
                range: $location->range,
                newText: $params->newName,
            );
        }

        return new WorkspaceEdit(changes: $changes);
    }
}

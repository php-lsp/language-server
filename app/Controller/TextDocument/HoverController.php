<?php

declare(strict_types=1);

namespace App\Controller\TextDocument;

use App\Core\Contracts\Documentation\DocumentationConsumer;
use App\Core\Contracts\Documentation\DocumentationContext;
use App\Core\Contracts\Documentation\DocumentationContributor;
use Lsp\Extension\DocumentManager\Editor\EditorInterface;
use Lsp\Kernel\Attribute\AsController;
use Lsp\Protocol\Type\Hover;
use Lsp\Protocol\Type\HoverParams;
use Lsp\Router\Attribute\Route;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;

#[AsController, Route('textDocument/hover')]
final class HoverController
{
    /**
     * @var list<DocumentationContributor>
     */
    private array $contributors;

    public function __construct(
        #[AutowireIterator('lsp.documentationContributors')]
        iterable $contributors,
    ) {
        $this->contributors = iterator_to_array($contributors);
    }

    public function __invoke(EditorInterface $editor, HoverParams $params): Hover
    {
        $context = new DocumentationContext($params->textDocument, $params->position, $editor);
        $consumer = new DocumentationConsumer();

        foreach ($this->contributors as $contributor) {
            $contributor->contribute($context, $consumer);
        }

        return new Hover($consumer->results);
    }
}

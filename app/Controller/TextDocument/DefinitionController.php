<?php

declare(strict_types=1);

namespace App\Controller\TextDocument;

use App\Core\Contracts\Definition\DefinitionConsumer;
use App\Core\Contracts\Definition\DefinitionContext;
use App\Core\Contracts\Definition\DefinitionContributor;
use Lsp\Extension\DocumentManager\Editor\EditorInterface;
use Lsp\Kernel\Attribute\AsController;
use Lsp\Protocol\Type\DefinitionParams;
use Lsp\Router\Attribute\Route;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;

#[AsController, Route('textDocument/definition')]
final class DefinitionController
{
    /**
     * @var list<DefinitionContributor>
     */
    private array $contributors;

    public function __construct(
        #[AutowireIterator('lsp.definitionContributors')]
        iterable $contributors,
    ) {
        $this->contributors = iterator_to_array($contributors);
    }

    public function __invoke(EditorInterface $editor, DefinitionParams $params): array
    {
        $context = new DefinitionContext($params->textDocument, $params->position, $editor);
        $consumer = new DefinitionConsumer();

        foreach ($this->contributors as $contributor) {
            $contributor->contribute($context, $consumer);
        }

        return $consumer->results;
    }
}

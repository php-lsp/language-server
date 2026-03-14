<?php

declare(strict_types=1);

namespace App\Controller\TextDocument;

use App\Core\Contracts\Declaration\DeclarationConsumer;
use App\Core\Contracts\Declaration\DeclarationContext;
use App\Core\Contracts\Declaration\DeclarationContributor;
use Lsp\Extension\DocumentManager\Editor\EditorInterface;
use Lsp\Kernel\Attribute\AsController;
use Lsp\Protocol\Type\DeclarationParams;
use Lsp\Router\Attribute\Route;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;

#[AsController, Route('textDocument/declaration')]
final class DeclarationController
{
    /**
     * @var list<DeclarationContributor>
     */
    private array $contributors;

    public function __construct(
        #[AutowireIterator('lsp.declarationContributors')]
        iterable $contributors,
    ) {
        $this->contributors = iterator_to_array($contributors);
    }

    public function __invoke(EditorInterface $editor, DeclarationParams $params): array
    {
        //        dump('ReferenceParams: ', $params);

        $context = new DeclarationContext($params->textDocument, $params->position, $editor);
        $consumer = new DeclarationConsumer();

        foreach ($this->contributors as $contributor) {
            $contributor->contribute($context, $consumer);
        }

        return $consumer->results;
    }
}

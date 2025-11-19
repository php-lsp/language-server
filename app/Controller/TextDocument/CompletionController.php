<?php

declare(strict_types=1);

namespace App\Controller\TextDocument;

use App\Core\Contracts\Completion\CompletionConsumer;
use App\Core\Contracts\Completion\CompletionContext;
use App\Core\Contracts\Completion\CompletionContributor;
use Lsp\Extension\DocumentManager\Editor\EditorInterface;
use Lsp\Kernel\Attribute\AsController;
use Lsp\Protocol\Type\CompletionParams;
use Lsp\Router\Attribute\Route;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;

#[AsController, Route('textDocument/completion')]
final class CompletionController
{
    /**
     * @var list<CompletionContributor>
     */
    private array $contributors;

    public function __construct(
        #[AutowireIterator('lsp.completionContributors')]
        iterable $contributors,
    )
    {
        $this->contributors = iterator_to_array($contributors);
    }

    public function __invoke(EditorInterface $editor, CompletionParams $params)
    {
//        dump('CompletionParams: ', $params);

        $context = new CompletionContext($params->textDocument, $params->position, $editor);
        $consumer = new CompletionConsumer();

        foreach ($this->contributors as $contributor) {
            $contributor->contribute($context, $consumer);
        }

        return $consumer->results;
    }
}

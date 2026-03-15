<?php

declare(strict_types=1);

namespace App\Controller\TextDocument;

use App\Core\Contracts\CodeAction\CodeActionConsumer;
use App\Core\Contracts\CodeAction\CodeActionContext;
use App\Core\Contracts\CodeAction\CodeActionContributor;
use Lsp\Extension\DocumentManager\Editor\EditorInterface;
use Lsp\Kernel\Attribute\AsController;
use Lsp\Protocol\Type\CodeActionParams;
use Lsp\Router\Attribute\Route;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;

#[AsController, Route('textDocument/codeAction')]
final class CodeActionController
{
    /**
     * @var list<CodeActionContributor>
     */
    private array $contributors;

    public function __construct(
        #[AutowireIterator('lsp.codeActionContributors')]
        iterable $contributors,
    ) {
        $this->contributors = iterator_to_array($contributors);
    }

    /**
     * @return list<\Lsp\Protocol\Type\CodeAction>
     */
    public function __invoke(EditorInterface $editor, CodeActionParams $params): array
    {
        $context = new CodeActionContext(
            $params->textDocument,
            $params->range,
            $params->context,
            $editor,
        );
        $consumer = new CodeActionConsumer();

        foreach ($this->contributors as $contributor) {
            $contributor->contribute($context, $consumer);
        }

        return $consumer->results;
    }
}

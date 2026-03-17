<?php

declare(strict_types=1);

namespace App\Controller\TextDocument;

use App\Core\Contracts\CallHierarchy\CallHierarchyContributor;
use App\Core\Contracts\CallHierarchy\PrepareCallHierarchyConsumer;
use App\Core\Contracts\CallHierarchy\PrepareCallHierarchyContext;
use App\Module\Telemetry\TracerInterface;
use Lsp\Extension\DocumentManager\Editor\EditorInterface;
use Lsp\Kernel\Attribute\AsController;
use Lsp\Protocol\Type\CallHierarchyPrepareParams;
use Lsp\Router\Attribute\Route;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;

#[AsController, Route('textDocument/prepareCallHierarchy')]
final class PrepareCallHierarchyController
{
    /**
     * @var list<CallHierarchyContributor>
     */
    private array $contributors;

    public function __construct(
        #[AutowireIterator('lsp.callHierarchyContributors')]
        iterable $contributors,
        private readonly TracerInterface $tracer,
    ) {
        $this->contributors = iterator_to_array($contributors);
    }

    public function __invoke(EditorInterface $editor, CallHierarchyPrepareParams $params): ?array
    {
        return $this->tracer->trace('textDocument/prepareCallHierarchy', function () use ($editor, $params): ?array {
            $context = new PrepareCallHierarchyContext($params->textDocument, $params->position, $editor);
            $consumer = new PrepareCallHierarchyConsumer();

            foreach ($this->contributors as $contributor) {
                $this->tracer->trace($contributor::class, static function () use (
                    $contributor,
                    $context,
                    $consumer,
                ): void {
                    $contributor->prepare($context, $consumer);
                });
            }

            return $consumer->results === [] ? null : $consumer->results;
        });
    }
}

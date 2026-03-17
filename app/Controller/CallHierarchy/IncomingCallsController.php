<?php

declare(strict_types=1);

namespace App\Controller\CallHierarchy;

use App\Core\Contracts\CallHierarchy\CallHierarchyContributor;
use App\Core\Contracts\CallHierarchy\IncomingCallsConsumer;
use App\Core\Contracts\CallHierarchy\IncomingCallsContext;
use App\Module\Telemetry\TracerInterface;
use Lsp\Extension\DocumentManager\Editor\EditorInterface;
use Lsp\Kernel\Attribute\AsController;
use Lsp\Protocol\Type\CallHierarchyIncomingCallsParams;
use Lsp\Router\Attribute\Route;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;

#[AsController, Route('callHierarchy/incomingCalls')]
final class IncomingCallsController
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

    public function __invoke(EditorInterface $editor, CallHierarchyIncomingCallsParams $params): array
    {
        return $this->tracer->trace('callHierarchy/incomingCalls', function () use ($editor, $params): array {
            $context = new IncomingCallsContext($params->item, $editor);
            $consumer = new IncomingCallsConsumer();

            foreach ($this->contributors as $contributor) {
                $this->tracer->trace($contributor::class, static function () use (
                    $contributor,
                    $context,
                    $consumer,
                ): void {
                    $contributor->incomingCalls($context, $consumer);
                });
            }

            return $consumer->results;
        });
    }
}

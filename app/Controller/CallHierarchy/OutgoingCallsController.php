<?php

declare(strict_types=1);

namespace App\Controller\CallHierarchy;

use App\Core\Contracts\CallHierarchy\CallHierarchyContributor;
use App\Core\Contracts\CallHierarchy\OutgoingCallsConsumer;
use App\Core\Contracts\CallHierarchy\OutgoingCallsContext;
use App\Module\Telemetry\TracerInterface;
use Lsp\Extension\DocumentManager\Editor\EditorInterface;
use Lsp\Kernel\Attribute\AsController;
use Lsp\Protocol\Type\CallHierarchyOutgoingCallsParams;
use Lsp\Router\Attribute\Route;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;

#[AsController, Route('callHierarchy/outgoingCalls')]
final class OutgoingCallsController
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

    public function __invoke(EditorInterface $editor, CallHierarchyOutgoingCallsParams $params): array
    {
        return $this->tracer->trace('callHierarchy/outgoingCalls', function () use ($editor, $params): array {
            $context = new OutgoingCallsContext($params->item, $editor);
            $consumer = new OutgoingCallsConsumer();

            foreach ($this->contributors as $contributor) {
                $this->tracer->trace($contributor::class, static function () use (
                    $contributor,
                    $context,
                    $consumer,
                ): void {
                    $contributor->outgoingCalls($context, $consumer);
                });
            }

            return $consumer->results;
        });
    }
}

<?php

declare(strict_types=1);

namespace App\Controller\TextDocument;

use App\Core\Contracts\Implementation\ImplementationConsumer;
use App\Core\Contracts\Implementation\ImplementationContext;
use App\Core\Contracts\Implementation\ImplementationContributor;
use App\Module\Telemetry\TracerInterface;
use Lsp\Extension\DocumentManager\Editor\EditorInterface;
use Lsp\Kernel\Attribute\AsController;
use Lsp\Protocol\Type\ImplementationParams;
use Lsp\Router\Attribute\Route;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;

#[AsController, Route('textDocument/implementation')]
final class ImplementationController
{
    /**
     * @var list<ImplementationContributor>
     */
    private array $contributors;

    public function __construct(
        #[AutowireIterator('lsp.implementationContributors')]
        iterable $contributors,
        private readonly TracerInterface $tracer,
    ) {
        $this->contributors = iterator_to_array($contributors);
    }

    public function __invoke(EditorInterface $editor, ImplementationParams $params): array
    {
        return $this->tracer->trace('textDocument/implementation', function () use ($editor, $params): array {
            $context = new ImplementationContext($params->textDocument, $params->position, $editor);
            $consumer = new ImplementationConsumer();

            foreach ($this->contributors as $contributor) {
                $this->tracer->trace($contributor::class, static function () use (
                    $contributor,
                    $context,
                    $consumer,
                ): void {
                    $contributor->contribute($context, $consumer);
                });
            }

            return $consumer->results;
        });
    }
}

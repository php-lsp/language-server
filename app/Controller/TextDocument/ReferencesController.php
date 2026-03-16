<?php

declare(strict_types=1);

namespace App\Controller\TextDocument;

use App\Core\Contracts\References\ReferenceConsumer;
use App\Core\Contracts\References\ReferenceContext;
use App\Core\Contracts\References\ReferenceContributor;
use App\Module\Telemetry\TracerInterface;
use Lsp\Extension\DocumentManager\Editor\EditorInterface;
use Lsp\Kernel\Attribute\AsController;
use Lsp\Protocol\Type\ReferenceParams;
use Lsp\Router\Attribute\Route;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;

#[AsController, Route('textDocument/references')]
final class ReferencesController
{
    /**
     * @var list<ReferenceContributor>
     */
    private array $contributors;

    public function __construct(
        #[AutowireIterator('lsp.referenceContributors')]
        iterable $contributors,
        private readonly TracerInterface $tracer,
    ) {
        $this->contributors = iterator_to_array($contributors);
    }

    public function __invoke(EditorInterface $editor, ReferenceParams $params): array
    {
        return $this->tracer->trace('textDocument/references', function () use ($editor, $params): array {
            $context = new ReferenceContext($params->textDocument, $params->position, $editor);
            $consumer = new ReferenceConsumer();

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

<?php

declare(strict_types=1);

namespace App\Controller\TextDocument;

use App\Core\Contracts\Declaration\DeclarationConsumer;
use App\Core\Contracts\Declaration\DeclarationContext;
use App\Core\Contracts\Declaration\DeclarationContributor;
use App\Module\Telemetry\TracerInterface;
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
        private readonly TracerInterface $tracer,
    ) {
        $this->contributors = iterator_to_array($contributors);
    }

    public function __invoke(EditorInterface $editor, DeclarationParams $params): array
    {
        return $this->tracer->trace('textDocument/declaration', function () use ($editor, $params): array {
            $context = new DeclarationContext($params->textDocument, $params->position, $editor);
            $consumer = new DeclarationConsumer();

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

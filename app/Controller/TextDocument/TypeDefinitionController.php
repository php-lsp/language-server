<?php

declare(strict_types=1);

namespace App\Controller\TextDocument;

use App\Core\Contracts\TypeDefinition\TypeDefinitionConsumer;
use App\Core\Contracts\TypeDefinition\TypeDefinitionContext;
use App\Core\Contracts\TypeDefinition\TypeDefinitionContributor;
use App\Module\Telemetry\TracerInterface;
use Lsp\Extension\DocumentManager\Editor\EditorInterface;
use Lsp\Kernel\Attribute\AsController;
use Lsp\Protocol\Type\TypeDefinitionParams;
use Lsp\Router\Attribute\Route;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;

#[AsController, Route('textDocument/typeDefinition')]
final class TypeDefinitionController
{
    /**
     * @var list<TypeDefinitionContributor>
     */
    private array $contributors;

    public function __construct(
        #[AutowireIterator('lsp.typeDefinitionContributors')]
        iterable $contributors,
        private readonly TracerInterface $tracer,
    ) {
        $this->contributors = iterator_to_array($contributors);
    }

    public function __invoke(EditorInterface $editor, TypeDefinitionParams $params): array
    {
        return $this->tracer->trace('textDocument/typeDefinition', function () use ($editor, $params): array {
            $context = new TypeDefinitionContext($params->textDocument, $params->position, $editor);
            $consumer = new TypeDefinitionConsumer();

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

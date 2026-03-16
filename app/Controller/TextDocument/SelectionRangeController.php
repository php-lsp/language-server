<?php

declare(strict_types=1);

namespace App\Controller\TextDocument;

use App\Core\Contracts\SelectionRange\SelectionRangeConsumer;
use App\Core\Contracts\SelectionRange\SelectionRangeContext;
use App\Core\Contracts\SelectionRange\SelectionRangeContributor;
use App\Module\Telemetry\TracerInterface;
use Lsp\Extension\DocumentManager\Editor\EditorInterface;
use Lsp\Kernel\Attribute\AsController;
use Lsp\Protocol\Type\SelectionRange;
use Lsp\Protocol\Type\SelectionRangeParams;
use Lsp\Router\Attribute\Route;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;

#[AsController, Route('textDocument/selectionRange')]
final class SelectionRangeController
{
    /**
     * @var list<SelectionRangeContributor>
     */
    private array $contributors;

    /**
     * @param iterable<SelectionRangeContributor> $contributors
     */
    public function __construct(
        #[AutowireIterator('lsp.selectionRangeContributors')]
        iterable $contributors,
        private readonly TracerInterface $tracer,
    ) {
        $this->contributors = array_values(iterator_to_array($contributors));
    }

    /**
     * @return list<SelectionRange|null>
     */
    public function __invoke(EditorInterface $editor, SelectionRangeParams $params): array
    {
        /** @var list<SelectionRange|null> */
        return $this->tracer->trace(
            'textDocument/selectionRange',
            /** @return list<SelectionRange|null> */ function () use ($editor, $params): array {
                $context = new SelectionRangeContext($params->textDocument, $params->positions, $editor);
                $consumer = new SelectionRangeConsumer();

                foreach ($this->contributors as $contributor) {
                    $this->tracer->trace($contributor::class, static function () use (
                        $contributor,
                        $context,
                        $consumer,
                    ): void {
                        $contributor->contribute($context, $consumer);
                    });
                }

                // Return results in the same order as the input positions.
                // For positions without a result, return a minimal selection range.
                $results = [];
                foreach ($params->positions as $index => $position) {
                    $results[] = $consumer->results[$index] ?? null;
                }

                return $results;
            },
        );
    }
}

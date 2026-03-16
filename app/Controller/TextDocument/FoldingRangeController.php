<?php

declare(strict_types=1);

namespace App\Controller\TextDocument;

use App\Core\Contracts\FoldingRange\FoldingRangeConsumer;
use App\Core\Contracts\FoldingRange\FoldingRangeContext;
use App\Core\Contracts\FoldingRange\FoldingRangeContributor;
use App\Module\PsiFile\InMemoryPsiFileManager;
use App\Module\Telemetry\TracerInterface;
use Lsp\Extension\DocumentManager\Editor\EditorInterface;
use Lsp\Kernel\Attribute\AsController;
use Lsp\Protocol\Type\FoldingRange;
use Lsp\Protocol\Type\FoldingRangeParams;
use Lsp\Router\Attribute\Route;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;

#[AsController, Route('textDocument/foldingRange')]
final class FoldingRangeController
{
    /**
     * @var list<FoldingRangeContributor>
     */
    private readonly array $contributors;

    /**
     * @param iterable<FoldingRangeContributor> $contributors
     */
    public function __construct(
        #[AutowireIterator('lsp.foldingRangeContributors')]
        iterable $contributors,
        private readonly InMemoryPsiFileManager $fileManager,
        private readonly TracerInterface $tracer,
    ) {
        /** @var list<FoldingRangeContributor> */
        $list = \iterator_to_array($contributors);
        $this->contributors = $list;
    }

    /**
     * @return list<FoldingRange>
     */
    public function __invoke(EditorInterface $editor, FoldingRangeParams $params): array
    {
        return $this->tracer->trace(
            'textDocument/foldingRange',
            /** @return list<FoldingRange> */ function () use ($editor, $params): array {
                $context = new FoldingRangeContext(
                    $params->textDocument,
                    $editor,
                    $this->fileManager,
                );
                $consumer = new FoldingRangeConsumer();

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
            },
        );
    }
}

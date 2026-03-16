<?php

declare(strict_types=1);

namespace App\Controller\TextDocument;

use App\Core\Contracts\InlayHint\InlayHintConsumer;
use App\Core\Contracts\InlayHint\InlayHintContext;
use App\Core\Contracts\InlayHint\InlayHintContributor;
use App\Core\Contracts\PsiFile\PsiFileManagerInterface;
use App\Module\Telemetry\TracerInterface;
use Lsp\Extension\DocumentManager\Editor\EditorInterface;
use Lsp\Kernel\Attribute\AsController;
use Lsp\Protocol\Type\InlayHint;
use Lsp\Protocol\Type\InlayHintParams;
use Lsp\Router\Attribute\Route;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;

#[AsController, Route('textDocument/inlayHint')]
final class InlayHintController
{
    /**
     * @var list<InlayHintContributor>
     */
    private readonly array $contributors;

    /**
     * @param iterable<InlayHintContributor> $contributors
     */
    public function __construct(
        #[AutowireIterator('lsp.inlayHintContributors')]
        iterable $contributors,
        private readonly PsiFileManagerInterface $fileManager,
        private readonly TracerInterface $tracer,
    ) {
        /** @var list<InlayHintContributor> */
        $list = \iterator_to_array($contributors);
        $this->contributors = $list;
    }

    /**
     * @return list<InlayHint>
     */
    public function __invoke(EditorInterface $editor, InlayHintParams $params): array
    {
        return $this->tracer->trace(
            'textDocument/inlayHint',
            /** @return list<InlayHint> */ function () use ($editor, $params): array {
                $context = new InlayHintContext(
                    $params->textDocument,
                    $params->range,
                    $editor,
                    $this->fileManager,
                );
                $consumer = new InlayHintConsumer();

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

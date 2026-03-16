<?php

declare(strict_types=1);

namespace App\Controller\TextDocument;

use App\Core\Contracts\Highlight\DocumentHighlightConsumer;
use App\Core\Contracts\Highlight\DocumentHighlightContext;
use App\Core\Contracts\Highlight\DocumentHighlightContributor;
use App\Core\Contracts\PsiFile\PsiFileManagerInterface;
use App\Module\Telemetry\TracerInterface;
use Lsp\Extension\DocumentManager\Editor\EditorInterface;
use Lsp\Kernel\Attribute\AsController;
use Lsp\Protocol\Type\DocumentHighlightParams;
use Lsp\Router\Attribute\Route;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;

#[AsController, Route('textDocument/documentHighlight')]
final class DocumentHighlightController
{
    /**
     * @var list<DocumentHighlightContributor>
     */
    private array $contributors;

    public function __construct(
        #[AutowireIterator('lsp.documentHighlightContributors')]
        iterable $contributors,
        private PsiFileManagerInterface $fileManager,
        private readonly TracerInterface $tracer,
    ) {
        $this->contributors = iterator_to_array($contributors);
    }

    public function __invoke(EditorInterface $editor, DocumentHighlightParams $params): array
    {
        return $this->tracer->trace('textDocument/documentHighlight', function () use ($editor, $params): array {
            $context = new DocumentHighlightContext(
                $params->textDocument,
                $params->position,
                $editor,
                $this->fileManager,
            );
            $consumer = new DocumentHighlightConsumer();

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

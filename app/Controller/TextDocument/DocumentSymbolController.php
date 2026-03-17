<?php

declare(strict_types=1);

namespace App\Controller\TextDocument;

use App\Core\Contracts\DocumentSymbol\DocumentSymbolConsumer;
use App\Core\Contracts\DocumentSymbol\DocumentSymbolContext;
use App\Core\Contracts\DocumentSymbol\DocumentSymbolContributor;
use App\Core\Contracts\PsiFile\PsiFileManagerInterface;
use App\Module\Telemetry\TracerInterface;
use Lsp\Extension\DocumentManager\Editor\EditorInterface;
use Lsp\Kernel\Attribute\AsController;
use Lsp\Protocol\Type\DocumentSymbol;
use Lsp\Protocol\Type\DocumentSymbolParams;
use Lsp\Router\Attribute\Route;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;

#[AsController, Route('textDocument/documentSymbol')]
final class DocumentSymbolController
{
    /**
     * @var list<DocumentSymbolContributor>
     */
    private readonly array $contributors;

    /**
     * @param iterable<DocumentSymbolContributor> $contributors
     */
    public function __construct(
        #[AutowireIterator('lsp.documentSymbolContributors')]
        iterable $contributors,
        private readonly PsiFileManagerInterface $fileManager,
        private readonly TracerInterface $tracer,
    ) {
        /** @var list<DocumentSymbolContributor> */
        $list = \iterator_to_array($contributors);
        $this->contributors = $list;
    }

    /**
     * @return list<DocumentSymbol>
     */
    public function __invoke(EditorInterface $editor, DocumentSymbolParams $params): array
    {
        return $this->tracer->trace(
            'textDocument/documentSymbol',
            /** @return list<DocumentSymbol> */ function () use ($editor, $params): array {
                $context = new DocumentSymbolContext(
                    $params->textDocument,
                    $editor,
                    $this->fileManager,
                );
                $consumer = new DocumentSymbolConsumer();

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

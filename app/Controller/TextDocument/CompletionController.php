<?php

declare(strict_types=1);

namespace App\Controller\TextDocument;

use App\Core\Contracts\Completion\CompletionConsumer;
use App\Core\Contracts\Completion\CompletionContext;
use App\Core\Contracts\Completion\CompletionContributor;
use App\Core\Contracts\PsiFile\PsiFileManagerInterface;
use App\Module\Telemetry\TracerInterface;
use Lsp\Extension\DocumentManager\Editor\EditorInterface;
use Lsp\Kernel\Attribute\AsController;
use Lsp\Protocol\Type\CompletionItem;
use Lsp\Protocol\Type\CompletionParams;
use Lsp\Router\Attribute\Route;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;

#[AsController, Route('textDocument/completion')]
final class CompletionController
{
    /**
     * @var list<CompletionContributor>
     */
    private array $contributors;

    public function __construct(
        #[AutowireIterator('lsp.completionContributors')]
        iterable $contributors,
        private LoggerInterface $logger,
        private PsiFileManagerInterface $fileManager,
        private TracerInterface $tracer,
    ) {
        $this->contributors = iterator_to_array($contributors);
    }

    public function __invoke(EditorInterface $editor, CompletionParams $params): array
    {
        return $this->tracer->trace('textDocument/completion', function () use ($editor, $params): array {
            $context = new CompletionContext($params->textDocument, $params->position, $editor, $this->fileManager);

            $results = [];
            $groupIndex = 0;
            foreach ($this->contributors as $contributor) {
                $consumer = new CompletionConsumer();
                $sortPrefix = str_pad((string) $groupIndex, 3, '0', STR_PAD_LEFT);

                $this->tracer->trace($contributor::class, function () use ($contributor, $context, $consumer): void {
                    try {
                        $contributor->contribute($context, $consumer);
                    } catch (\Throwable $e) {
                        $this->logger->error($e->getMessage());
                    }
                });

                foreach ($consumer->results as $item) {
                    $results[] = new CompletionItem(
                        label: $item->label,
                        labelDetails: $item->labelDetails,
                        kind: $item->kind,
                        tags: $item->tags,
                        detail: $item->detail,
                        documentation: $item->documentation,
                        deprecated: $item->deprecated,
                        preselect: $item->preselect,
                        sortText: $sortPrefix . ($item->sortText ?? $item->label),
                        filterText: $item->filterText,
                        insertText: $item->insertText,
                        insertTextFormat: $item->insertTextFormat,
                        insertTextMode: $item->insertTextMode,
                        textEdit: $item->textEdit,
                        textEditText: $item->textEditText,
                        additionalTextEdits: $item->additionalTextEdits,
                        commitCharacters: $item->commitCharacters,
                        command: $item->command,
                        data: $item->data,
                    );
                }

                ++$groupIndex;
            }

            return $results;
        });
    }
}

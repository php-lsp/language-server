<?php

declare(strict_types=1);

namespace App\Controller\TextDocument;

use App\Core\Contracts\Completion\CompletionConsumer;
use App\Core\Contracts\Completion\CompletionContext;
use App\Core\Contracts\Completion\CompletionContributor;
use App\Module\PsiFile\InMemoryPsiFileManager;
use App\Module\Telemetry\TracerInterface;
use Lsp\Extension\DocumentManager\Editor\EditorInterface;
use Lsp\Kernel\Attribute\AsController;
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
        private InMemoryPsiFileManager $fileManager,
        private TracerInterface $tracer,
    ) {
        $this->contributors = iterator_to_array($contributors);
    }

    public function __invoke(EditorInterface $editor, CompletionParams $params): array
    {
        return $this->tracer->trace('textDocument/completion', function () use ($editor, $params): array {
            $context = new CompletionContext($params->textDocument, $params->position, $editor, $this->fileManager);

            $results = [];
            foreach ($this->contributors as $contributor) {
                $consumer = new CompletionConsumer();

                $this->tracer->trace($contributor::class, function () use ($contributor, $context, $consumer): void {
                    try {
                        $contributor->contribute($context, $consumer);
                    } catch (\Throwable $e) {
                        $this->logger->error($e->getMessage());
                    }
                });

                $results[] = $consumer->results;
            }

            return array_merge(...$results);
        });
    }
}

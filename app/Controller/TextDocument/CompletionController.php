<?php

declare(strict_types=1);

namespace App\Controller\TextDocument;

use App\Core\Contracts\Completion\CompletionConsumer;
use App\Core\Contracts\Completion\CompletionContext;
use App\Core\Contracts\Completion\CompletionContributor;
use App\Module\PsiFile\InMemoryPsiFileManager;
use Lsp\Extension\DocumentManager\Editor\EditorInterface;
use Lsp\Kernel\Attribute\AsController;
use Lsp\Protocol\Type\CompletionParams;
use Lsp\Router\Attribute\Route;
use Psr\Log\LoggerInterface;
use React\Promise\PromiseInterface;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;

use function React\Async\async;
use function React\Async\await;
use function React\Promise\all;
use function React\Promise\Timer\timeout;

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
    ) {
        $this->contributors = iterator_to_array($contributors);
    }

    public function __invoke(EditorInterface $editor, CompletionParams $params)
    {
        $context = new CompletionContext($params->textDocument, $params->position, $editor, $this->fileManager);

        $promises = [];
        foreach ($this->contributors as $contributor) {
            $promises[] = $this->runContributor($contributor, $context);
        }

        $results = await(all($promises));

        return array_merge(...$results);
    }

    /**
     * @return PromiseInterface<array>
     */
    private function runContributor(
        CompletionContributor $contributor,
        CompletionContext $context,
    ): PromiseInterface {
        $consumer = new CompletionConsumer();

        $onRejected = function (\Throwable $e) use ($consumer) {
            $this->logger->error($e);

            return $consumer->results;
        };

        return timeout(
            async(
                static function () use ($contributor, $context, $consumer) {
                    $contributor->contribute($context, $consumer);

                    return $consumer->results;
                },
            )()->catch($onRejected),
            time: 1.0,
        )->catch($onRejected);
    }
}

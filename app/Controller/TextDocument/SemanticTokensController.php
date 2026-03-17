<?php

declare(strict_types=1);

namespace App\Controller\TextDocument;

use App\Core\Contracts\PsiFile\PsiFileManagerInterface;
use App\Core\Contracts\SemanticToken\SemanticTokenConsumer;
use App\Core\Contracts\SemanticToken\SemanticTokenContext;
use App\Core\Contracts\SemanticToken\SemanticTokenContributor;
use App\Module\SemanticToken\SemanticTokenEncoder;
use App\Module\Telemetry\TracerInterface;
use Lsp\Extension\DocumentManager\Editor\EditorInterface;
use Lsp\Kernel\Attribute\AsController;
use Lsp\Protocol\Type\SemanticTokens;
use Lsp\Protocol\Type\SemanticTokensParams;
use Lsp\Router\Attribute\Route;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;

#[AsController, Route('textDocument/semanticTokens/full')]
final class SemanticTokensController
{
    /**
     * @var list<SemanticTokenContributor>
     */
    private readonly array $contributors;

    /**
     * @param iterable<SemanticTokenContributor> $contributors
     */
    public function __construct(
        #[AutowireIterator('lsp.semanticTokenContributors')]
        iterable $contributors,
        private readonly PsiFileManagerInterface $fileManager,
        private readonly TracerInterface $tracer,
    ) {
        /** @var list<SemanticTokenContributor> */
        $list = \iterator_to_array($contributors);
        $this->contributors = $list;
    }

    public function __invoke(EditorInterface $editor, SemanticTokensParams $params): SemanticTokens
    {
        return $this->tracer->trace(
            'textDocument/semanticTokens/full',
            function () use ($editor, $params): SemanticTokens {
                $context = new SemanticTokenContext(
                    $params->textDocument,
                    $editor,
                    $this->fileManager,
                );
                $consumer = new SemanticTokenConsumer();

                foreach ($this->contributors as $contributor) {
                    $this->tracer->trace($contributor::class, static function () use (
                        $contributor,
                        $context,
                        $consumer,
                    ): void {
                        $contributor->contribute($context, $consumer);
                    });
                }

                return new SemanticTokens(
                    data: SemanticTokenEncoder::encode($consumer->tokens),
                );
            },
        );
    }
}

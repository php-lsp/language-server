<?php

declare(strict_types=1);

namespace App\Controller\TextDocument;

use App\Core\Contracts\Signature\SignatureConsumer;
use App\Core\Contracts\Signature\SignatureContext;
use App\Core\Contracts\Signature\SignatureContributor;
use App\Module\Telemetry\TracerInterface;
use Lsp\Extension\DocumentManager\Editor\EditorInterface;
use Lsp\Kernel\Attribute\AsController;
use Lsp\Protocol\Type\SignatureHelp;
use Lsp\Protocol\Type\SignatureHelpParams;
use Lsp\Router\Attribute\Route;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;

#[AsController, Route('textDocument/signatureHelp')]
final class SignatureHelpController
{
    /**
     * @var list<SignatureContributor>
     */
    private array $contributors;

    public function __construct(
        #[AutowireIterator('lsp.signatureContributors')]
        iterable $contributors,
        private readonly TracerInterface $tracer,
    ) {
        $this->contributors = iterator_to_array($contributors);
    }

    public function __invoke(EditorInterface $editor, SignatureHelpParams $params): ?SignatureHelp
    {
        return $this->tracer->trace('textDocument/signatureHelp', function () use ($editor, $params): SignatureHelp {
            $context = new SignatureContext($params->textDocument, $params->position, $editor);
            $consumer = new SignatureConsumer();

            foreach ($this->contributors as $contributor) {
                $this->tracer->trace($contributor::class, static function () use (
                    $contributor,
                    $context,
                    $consumer,
                ): void {
                    $contributor->contribute($context, $consumer);
                });
            }

            return new SignatureHelp(
                signatures: $consumer->results,
            );
        });
    }
}

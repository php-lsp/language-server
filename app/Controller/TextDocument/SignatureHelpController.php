<?php

namespace App\Controller\TextDocument;

use App\Core\Contracts\Completion\CompletionConsumer;
use App\Core\Contracts\Completion\CompletionContext;
use App\Core\Contracts\References\ReferenceContributor;
use App\Core\Contracts\Signature\SignatureConsumer;
use App\Core\Contracts\Signature\SignatureContext;
use App\Core\Contracts\Signature\SignatureContributor;
use Lsp\Extension\DocumentManager\Editor\EditorInterface;
use Lsp\Kernel\Attribute\AsController;
use Lsp\Protocol\Type\ParameterInformation;
use Lsp\Protocol\Type\SignatureHelp;
use Lsp\Protocol\Type\SignatureHelpParams;
use Lsp\Protocol\Type\SignatureInformation;
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
    )
    {
        $this->contributors = iterator_to_array($contributors);
    }

    public function __invoke(EditorInterface $editor, SignatureHelpParams $params): ?SignatureHelp
    {
        $context = new SignatureContext($params->textDocument, $params->position, $editor);
        $consumer = new SignatureConsumer();

        foreach ($this->contributors as $contributor) {
            $contributor->contribute($context, $consumer);
        }

        return new SignatureHelp(
            signatures: $consumer->results,
//            activeSignature: 0,
//            activeParameter: $call['currentParamIndex']
        );
    }
}

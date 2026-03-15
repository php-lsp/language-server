<?php

declare(strict_types=1);

namespace App\Module\Signature;

use App\Core\Contracts\Signature\AsSignatureContributor;
use App\Core\Contracts\Signature\SignatureConsumer;
use App\Core\Contracts\Signature\SignatureContext;
use App\Core\Contracts\Signature\SignatureContributor;
use App\Module\Indexing\Data\FunctionData;
use App\Module\Indexing\Indexer\FunctionIndexer;
use App\Module\Indexing\IndexLookup;
use App\Module\PsiFile\InMemoryPsiFileManager;
use Lsp\Protocol\Type\ParameterInformation;
use Lsp\Protocol\Type\SignatureInformation;
use PhpParser\Node\Expr\FuncCall;

#[AsSignatureContributor]
final class FunctionSignatureContributor implements SignatureContributor
{
    public function __construct(
        private readonly InMemoryPsiFileManager $fileManager,
        private readonly IndexLookup $indexLookup,
    ) {}

    public function contribute(SignatureContext $context, SignatureConsumer $consumer): void
    {
        $file = $this->fileManager->findPsiFile($context->editor, $context->textDocumentIdentifier);
        if ($file === null) {
            return;
        }

        $nodes = $file->findAtPosition($context->position);

        $node = array_find($nodes, static fn(mixed $node): bool => $node instanceof FuncCall);
        if (!$node instanceof FuncCall) {
            return;
        }

        $functionName = $node->name->toString();

        foreach ($this->indexLookup->findByKey(FunctionIndexer::class) as $value) {
            /** @var FunctionData $data */
            $data = $value->value;

            if ($data->fqn !== $functionName) {
                continue;
            }

            $paramLabels = [];
            $parameters = [];
            foreach ($data->parameters as $param) {
                $label = ($param->type !== null ? $param->type . ' ' : '') . $param->name;
                $paramLabels[] = $label;
                $parameters[] = new ParameterInformation(
                    label: $label,
                    documentation: $param->type ?? '',
                );
            }

            $returnType = $data->returnType ?? 'mixed';
            $signature = sprintf('%s(%s): %s', $data->fqn, implode(', ', $paramLabels), $returnType);

            $consumer(
                new SignatureInformation(
                    label: $signature,
                    documentation: '',
                    parameters: $parameters,
                ),
            );
        }
    }
}

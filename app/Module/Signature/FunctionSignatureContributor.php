<?php

declare(strict_types=1);

namespace App\Module\Signature;

use App\Core\Contracts\Completion\AsCompletionContributor;
use App\Core\Contracts\Completion\CompletionConsumer;
use App\Core\Contracts\Completion\CompletionContext;
use App\Core\Contracts\Completion\CompletionContributor;
use App\Core\Contracts\Signature\AsSignatureContributor;
use App\Core\Contracts\Signature\SignatureConsumer;
use App\Core\Contracts\Signature\SignatureContext;
use App\Core\Contracts\Signature\SignatureContributor;
use App\Module\Indexing\Indexer\FunctionIndexer;
use App\Module\Indexing\IndexLookup;
use App\Module\PsiFile\InMemoryPsiFileManager;
use App\Module\PsiFile\PHPPsiFile;
use Lsp\Protocol\Type\CompletionItem;
use Lsp\Protocol\Type\CompletionItemKind;
use Lsp\Protocol\Type\ParameterInformation;
use Lsp\Protocol\Type\SignatureInformation;
use Lsp\Protocol\Type\TextDocumentIdentifier;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Stmt\Function_;

#[AsSignatureContributor]
final class FunctionSignatureContributor implements SignatureContributor
{
    public function __construct(
        private readonly InMemoryPsiFileManager $fileManager,
        private readonly IndexLookup $indexLookup,
    )
    {
    }

    public function contribute(SignatureContext $context, SignatureConsumer $consumer): void
    {
        $file = $this->fileManager->findPsiFile($context->editor, $context->textDocumentIdentifier);
        $nodes = $file->findAtPosition($context->position);
        /**
         * @var FuncCall|null
         */
        $node = array_find($nodes, fn($node) => match (true) {
            $node instanceof FuncCall => true,
            default => false,
        });
        if ($node === null) {
            return;
        }

        foreach ($this->indexLookup->findByKey(FunctionIndexer::class) as $key => $value) {
            if ($value->value[0] !== $node->name->toString()) {
                continue;
            }
            $source = $this->fileManager->findPsiFile($context->editor, new TextDocumentIdentifier($value->uri));

            // Найти определение функции
            $definition = $this->findFunctionDefinition($source, $value->value[1]);

            if (!$definition) {
                continue;
            }

            $parameters = [];
            foreach ($definition['params'] as $param) {
                $parameters[] = new ParameterInformation(
                    label: $param['name'],
                    documentation: $param['doc'] ?? ''
                );
            }

            $consumer(
                new SignatureInformation(
                    label: $definition['signature'],
                    documentation: $definition['doc'] ?? '',
                    parameters: $parameters
                )
            );
        }
    }


    private function findFunctionCall(string $uri, $position): ?array
    {
        // Логика поиска вызова функции
        return [
            'name' => 'myFunction',
            'currentParamIndex' => 1
        ];
    }

    private function findFunctionDefinition(PHPPsiFile $file, int $position): ?array
    {
        $nodes = $file->findAtPosition($position);

        /**
         * @var Function_|null $node
         */
        $node = array_find($nodes, fn($node) => match (true) {
            $node instanceof Function_ => true,
            default => false,
        });
        if ($node === null) {
            return null;
        }

        $params = array_map(
            fn($param) => sprintf('%s $%s', $param->getType(), $param->var->name),
            $node->params,
        );

        $signature = sprintf('%s(%s): %s', $node->name->toString(), implode(', ', $params), $node->returnType->toString());

        return [
//            'signature' => 'myFunction(string $param1, int $param2): void',
            'signature' => $signature,
//            'params' => [
//                ['name' => '$param1', 'doc' => 'First parameter'],
//                ['name' => '$param2', 'doc' => 'Second parameter'],
//            ],
            'params' => array_map(
                fn($param) => ['name' => $param->var->name, 'doc' => $param->type->toString()],
                $params,
            ),
            'doc' => $node->getDocComment()->getReformattedText(),
        ];
    }
}

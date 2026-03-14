<?php

declare(strict_types=1);

namespace App\Module\Signature;

use App\Core\Contracts\Signature\AsSignatureContributor;
use App\Core\Contracts\Signature\SignatureConsumer;
use App\Core\Contracts\Signature\SignatureContext;
use App\Core\Contracts\Signature\SignatureContributor;
use App\Module\Indexing\Indexer\FunctionIndexer;
use App\Module\Indexing\IndexLookup;
use App\Module\PsiFile\InMemoryPsiFileManager;
use App\Module\PsiFile\PHPPsiFile;
use Lsp\Protocol\Type\ParameterInformation;
use Lsp\Protocol\Type\SignatureInformation;
use Lsp\Protocol\Type\TextDocumentIdentifier;
use PhpParser\Node;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Stmt\Function_;

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

        $node = array_find($nodes, static fn($node) => $node instanceof FuncCall);
        if (!$node instanceof FuncCall) {
            return;
        }

        if (!$node->name instanceof Node\Name) {
            return;
        }
        $funcName = $node->name->toString();

        foreach ($this->indexLookup->findByKey(FunctionIndexer::class) as $value) {
            /** @var array{string, int} $entryValue */
            $entryValue = $value->value;
            if ($entryValue[0] !== $funcName) {
                continue;
            }

            /** @var non-empty-string $uri */
            $uri = $value->uri;
            $source = $this->fileManager->findPsiFile($context->editor, new TextDocumentIdentifier($uri));
            if ($source === null) {
                continue;
            }

            $definition = $this->findFunctionDefinition($source, $entryValue[1]);
            if ($definition === null) {
                continue;
            }

            $consumer($definition);
        }
    }

    private function findFunctionDefinition(PHPPsiFile $file, int $position): ?SignatureInformation
    {
        $nodes = $file->findAtPosition($position);

        $node = array_find($nodes, static fn($node) => $node instanceof Function_);
        if (!$node instanceof Function_) {
            return null;
        }

        $paramLabels = [];
        $parameters = [];
        foreach ($node->params as $param) {
            $typeStr = self::typeToString($param->type);
            $varName = $param->var instanceof Node\Expr\Variable && is_string($param->var->name)
                ? $param->var->name
                : 'unknown';
            $paramLabel = $typeStr !== '' ? sprintf('%s $%s', $typeStr, $varName) : '$' . $varName;
            $paramLabels[] = $paramLabel;
            $parameters[] = new ParameterInformation(
                label: '$' . $varName,
                documentation: $typeStr,
            );
        }

        $returnType = self::typeToString($node->returnType);
        $returnSuffix = $returnType !== '' ? ': ' . $returnType : '';
        $signature = sprintf('%s(%s)%s', $node->name->toString(), implode(', ', $paramLabels), $returnSuffix);

        $doc = '';
        $docComment = $node->getDocComment();
        if ($docComment !== null) {
            $doc = $docComment->getReformattedText();
        }

        return new SignatureInformation(
            label: $signature,
            documentation: $doc,
            parameters: $parameters,
        );
    }

    public static function typeToString(Node\Identifier|Node\Name|Node\ComplexType|null $type): string
    {
        if ($type === null) {
            return '';
        }

        if ($type instanceof Node\Identifier || $type instanceof Node\Name) {
            return $type->toString();
        }

        if ($type instanceof Node\NullableType) {
            return '?' . self::typeToString($type->type);
        }

        if ($type instanceof Node\UnionType) {
            return implode('|', array_map(
                static fn(Node\Identifier|Node\IntersectionType|Node\Name $t): string => match (true) {
                    $t instanceof Node\Identifier, $t instanceof Node\Name => $t->toString(),
                    $t instanceof Node\IntersectionType => self::typeToString($t),
                },
                $type->types,
            ));
        }

        if ($type instanceof Node\IntersectionType) {
            return implode('&', array_map(
                static fn(Node\Identifier|Node\Name $t): string => $t->toString(),
                $type->types,
            ));
        }

        return 'mixed';
    }
}

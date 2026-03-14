<?php

declare(strict_types=1);

namespace App\Module\Signature;

use App\Core\Contracts\Signature\AsSignatureContributor;
use App\Core\Contracts\Signature\SignatureConsumer;
use App\Core\Contracts\Signature\SignatureContext;
use App\Core\Contracts\Signature\SignatureContributor;
use App\Module\Indexing\Indexer\ClassMethodIndexer;
use App\Module\Indexing\IndexLookup;
use App\Module\PsiFile\InMemoryPsiFileManager;
use App\Module\PsiFile\Tree;
use Lsp\Protocol\Type\ParameterInformation;
use Lsp\Protocol\Type\SignatureInformation;
use Lsp\Protocol\Type\TextDocumentIdentifier;
use PhpParser\Node;
use PhpParser\NodeFinder;

#[AsSignatureContributor]
final class MethodSignatureContributor implements SignatureContributor
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

        $call = $this->findMethodCall($nodes);
        if ($call === null) {
            return;
        }

        [$className, $methodName] = $call;

        foreach ($this->indexLookup->findByKey(ClassMethodIndexer::class) as $entry) {
            if ($entry->value->className !== $className) {
                continue;
            }
            if ($entry->value->name !== $methodName) {
                continue;
            }

            /** @var non-empty-string $uri */
            $uri = $entry->uri;
            $source = $this->fileManager->findPsiFile(
                $context->editor,
                new TextDocumentIdentifier($uri),
            );
            if ($source === null) {
                continue;
            }

            $definition = $this->findMethodDefinition($source->ast->children, $className, $methodName);
            if ($definition === null) {
                continue;
            }

            $consumer($definition);
        }
    }

    /**
     * @param array<Node> $nodes
     *
     * @return array{string, string}|null
     */
    private function findMethodCall(array $nodes): ?array
    {
        foreach (array_reverse($nodes) as $node) {
            if ($node instanceof Node\Expr\StaticCall) {
                if (!$node->class instanceof Node\Name || !$node->name instanceof Node\Identifier) {
                    continue;
                }

                return [$node->class->toString(), $node->name->toString()];
            }

            if ($node instanceof Node\Expr\MethodCall) {
                if (!$node->name instanceof Node\Identifier) {
                    continue;
                }

                $className = $this->resolveVariableClass($node->var);
                if ($className === null) {
                    continue;
                }

                return [$className, $node->name->toString()];
            }
        }

        return null;
    }

    private function resolveVariableClass(Node\Expr $expr): ?string
    {
        if ($expr instanceof Node\Expr\Variable && $expr->name === 'this') {
            $class = Tree::parentOfType($expr, Node\Stmt\Class_::class);
            if ($class !== null) {
                return $class->namespacedName?->toString() ?? $class->name?->toString();
            }
        }

        if ($expr instanceof Node\Expr\New_ && $expr->class instanceof Node\Name) {
            return $expr->class->toString();
        }

        return null;
    }

    /**
     * @param array<Node> $nodes
     */
    private function findMethodDefinition(array $nodes, string $className, string $methodName): ?SignatureInformation
    {
        $finder = new NodeFinder();

        /** @var Node\Stmt\Class_[] $classes */
        $classes = $finder->findInstanceOf($nodes, Node\Stmt\Class_::class);

        foreach ($classes as $class) {
            $classFullName = $class->namespacedName?->toString() ?? $class->name?->toString();
            if ($classFullName !== $className) {
                continue;
            }

            foreach ($class->getMethods() as $method) {
                if ($method->name->toString() !== $methodName) {
                    continue;
                }

                return $this->buildSignatureInfo($className, $method);
            }
        }

        return null;
    }

    private function buildSignatureInfo(string $className, Node\Stmt\ClassMethod $method): SignatureInformation
    {
        $params = [];
        $paramLabels = [];
        foreach ($method->params as $param) {
            $typeStr = FunctionSignatureContributor::typeToString($param->type);
            $varName = $param->var instanceof Node\Expr\Variable && is_string($param->var->name)
                ? $param->var->name
                : 'unknown';
            $paramLabel = $typeStr !== '' ? sprintf('%s $%s', $typeStr, $varName) : '$' . $varName;
            $paramLabels[] = $paramLabel;
            $params[] = new ParameterInformation(
                label: '$' . $varName,
                documentation: $typeStr,
            );
        }

        $returnType = FunctionSignatureContributor::typeToString($method->returnType);
        $returnSuffix = $returnType !== '' ? ': ' . $returnType : '';
        $label = sprintf(
            '%s::%s(%s)%s',
            $className,
            $method->name->toString(),
            implode(', ', $paramLabels),
            $returnSuffix,
        );

        $doc = '';
        $docComment = $method->getDocComment();
        if ($docComment !== null) {
            $doc = $docComment->getReformattedText();
        }

        return new SignatureInformation(
            label: $label,
            documentation: $doc,
            parameters: $params,
        );
    }
}

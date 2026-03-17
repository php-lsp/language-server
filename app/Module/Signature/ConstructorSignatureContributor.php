<?php

declare(strict_types=1);

namespace App\Module\Signature;

use App\Core\Contracts\Signature\AsSignatureContributor;
use App\Core\Contracts\Signature\SignatureConsumer;
use App\Core\Contracts\Signature\SignatureContext;
use App\Core\Contracts\Signature\SignatureContributor;
use App\Module\Indexing\Data\NodeTypeExtractor;
use App\Module\Indexing\Indexer\ClassIndexer;
use App\Module\Indexing\IndexLookup;
use App\Module\PsiFile\InMemoryPsiFileManager;
use Lsp\Protocol\Type\ParameterInformation;
use Lsp\Protocol\Type\SignatureInformation;
use Lsp\Protocol\Type\TextDocumentIdentifier;
use Override;
use PhpParser\Node;
use PhpParser\NodeFinder;

#[AsSignatureContributor]
final class ConstructorSignatureContributor implements SignatureContributor
{
    public function __construct(
        private readonly InMemoryPsiFileManager $fileManager,
        private readonly IndexLookup $indexLookup,
    ) {}

    #[Override]
    public function contribute(SignatureContext $context, SignatureConsumer $consumer): void
    {
        $file = $this->fileManager->findPsiFile($context->editor, $context->textDocumentIdentifier);
        if ($file === null) {
            return;
        }

        $nodes = $file->findAtPosition($context->position);

        $className = $this->findNewExpression($nodes);
        if ($className === null) {
            return;
        }

        foreach ($this->indexLookup->findByField(ClassIndexer::class, 'fqn', $className) as $entry) {
            /** @var non-empty-string $uri */
            $uri = $entry->uri;
            $source = $this->fileManager->findPsiFile(
                $context->editor,
                new TextDocumentIdentifier($uri),
            );
            if ($source === null) {
                continue;
            }

            $definition = $this->findConstructorDefinition($source->getChildren(), $className);
            if ($definition === null) {
                continue;
            }

            $consumer($definition);
        }
    }

    /**
     * @param array<Node> $nodes
     */
    private function findNewExpression(array $nodes): ?string
    {
        foreach (array_reverse($nodes) as $node) {
            if ($node instanceof Node\Expr\New_ && $node->class instanceof Node\Name) {
                return $node->class->toString();
            }
        }

        return null;
    }

    /**
     * @param array<Node> $nodes
     */
    private function findConstructorDefinition(array $nodes, string $className): ?SignatureInformation
    {
        $finder = new NodeFinder();

        $classes = $finder->findInstanceOf($nodes, Node\Stmt\Class_::class);

        foreach ($classes as $class) {
            $classFullName = $class->namespacedName?->toString() ?? $class->name?->toString();
            if ($classFullName !== $className) {
                continue;
            }

            $constructor = $class->getMethod('__construct');
            if ($constructor === null) {
                return new SignatureInformation(
                    label: sprintf('new %s()', $className),
                    documentation: '',
                    parameters: [],
                );
            }

            return $this->buildSignatureInfo($className, $constructor);
        }

        return null;
    }

    private function buildSignatureInfo(string $className, Node\Stmt\ClassMethod $constructor): SignatureInformation
    {
        $params = [];
        $paramLabels = [];
        foreach ($constructor->params as $param) {
            $typeStr = NodeTypeExtractor::typeToString($param->type) ?? '';
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

        $label = sprintf('new %s(%s)', $className, implode(', ', $paramLabels));

        $doc = '';
        $docComment = $constructor->getDocComment();
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

<?php

declare(strict_types=1);

namespace App\Module\Definition;

use App\Core\Contracts\Definition\AsDefinitionContributor;
use App\Core\Contracts\Definition\DefinitionConsumer;
use App\Core\Contracts\Definition\DefinitionContext;
use App\Core\Contracts\Definition\DefinitionContributor;
use App\Module\Indexing\Data\MethodData;
use App\Module\Indexing\Indexer\ClassMethodIndexer;
use App\Module\Indexing\IndexLookup;
use App\Module\PsiFile\InMemoryPsiFileManager;
use App\Module\PsiFile\Tree;
use Lsp\Protocol\Type\Location;
use Lsp\Protocol\Type\Position;
use Lsp\Protocol\Type\Range;
use PhpParser\Node;

#[AsDefinitionContributor]
final class MethodDefinitionContributor implements DefinitionContributor
{
    public function __construct(
        private readonly IndexLookup $indexLookup,
        private readonly InMemoryPsiFileManager $fileManager,
    ) {}

    public function contribute(DefinitionContext $context, DefinitionConsumer $consumer): void
    {
        $editor = $context->editor;
        $file = $this->fileManager->findPsiFile($editor, $context->textDocumentIdentifier);
        if ($file === null) {
            return;
        }

        $element = $file->findLastAtPosition($context->position);

        $className = null;
        $methodName = null;

        // Handle static calls: Class::method()
        if ($element instanceof Node\Name\FullyQualified) {
            $node = Tree::parentOfType($element, Node\Expr\StaticCall::class);
            if ($node !== null) {
                $className = $node->class->toString();
                $methodName = $node->name->toString();
            }
        }

        // Handle instance method calls: $obj->method()
        if ($element instanceof Node\Identifier) {
            $methodCall = Tree::parentOfType($element, Node\Expr\MethodCall::class);
            if ($methodCall !== null && $methodCall->name === $element) {
                $methodName = $element->toString();

                // Resolve $this
                if ($methodCall->var instanceof Node\Expr\Variable && $methodCall->var->name === 'this') {
                    $classNode = Tree::parentOfType($element, Node\Stmt\Class_::class);
                    if ($classNode !== null) {
                        $className = $classNode->namespacedName?->toString() ?? $classNode->name?->toString();
                    }
                }
            }
        }

        if ($className === null || $methodName === null) {
            return;
        }

        $zeroPosition = new Position(0, 0);
        $startRange = new Range($zeroPosition, $zeroPosition);

        foreach ($this->indexLookup->findByKey(ClassMethodIndexer::class) as $value) {
            if ($value->key !== $className) {
                continue;
            }

            /** @var list<MethodData> $methods */
            $methods = $value->value;

            foreach ($methods as $method) {
                if ($method->name !== $methodName) {
                    continue;
                }

                $consumer(new Location(
                    uri: $value->uri,
                    range: $startRange,
                ));
            }
        }
    }
}

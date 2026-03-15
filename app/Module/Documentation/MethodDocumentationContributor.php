<?php

declare(strict_types=1);

namespace App\Module\Documentation;

use App\Core\Contracts\Documentation\AsDocumentationContributor;
use App\Core\Contracts\Documentation\DocumentationConsumer;
use App\Core\Contracts\Documentation\DocumentationContext;
use App\Core\Contracts\Documentation\DocumentationContributor;
use App\Module\Indexing\Indexer\ClassMethodIndexer;
use App\Module\Indexing\IndexLookup;
use App\Module\PsiFile\InMemoryPsiFileManager;
use App\Module\PsiFile\Tree;
use Override;
use PhpParser\Node;

#[AsDocumentationContributor]
final class MethodDocumentationContributor implements DocumentationContributor
{
    public function __construct(
        private readonly IndexLookup $indexLookup,
        private readonly InMemoryPsiFileManager $fileManager,
    ) {}

    #[Override]
    public function contribute(DocumentationContext $context, DocumentationConsumer $consumer): void
    {
        $file = $this->fileManager->findPsiFile($context->editor, $context->textDocumentIdentifier);
        if ($file === null) {
            return;
        }

        $element = $file->findLastAtPosition($context->position);
        if (!$element instanceof Node\Identifier) {
            return;
        }

        $methodName = $element->toString();
        $className = null;

        $methodCall = Tree::parentOfType($element, Node\Expr\MethodCall::class);
        if (
            $methodCall !== null
            && $methodCall->var instanceof Node\Expr\Variable
            && $methodCall->var->name === 'this'
        ) {
            $classNode = Tree::parentOfType($element, Node\Stmt\Class_::class);
            if ($classNode !== null) {
                $className = $classNode->namespacedName?->toString() ?? $classNode->name?->toString();
            }
        }

        $staticCall = Tree::parentOfType($element, Node\Expr\StaticCall::class);
        if ($staticCall !== null && $staticCall->class instanceof Node\Name) {
            $className = $staticCall->class->toString();
        }

        if ($className === null) {
            return;
        }

        foreach ($this->indexLookup->findByKey(ClassMethodIndexer::class) as $entry) {
            if ($entry->value->className !== $className || $entry->value->name !== $methodName) {
                continue;
            }

            $params = [];
            foreach ($entry->value->parameters as $param) {
                $paramStr = '';
                if ($param->type !== null) {
                    $paramStr .= $param->type . ' ';
                }
                if ($param->isVariadic) {
                    $paramStr .= '...';
                }
                $paramStr .= '$' . $param->name;
                $params[] = $paramStr;
            }

            $visibility = $entry->value->visibility->value;
            $static = $entry->value->isStatic ? 'static ' : '';
            $signature =
                $visibility . ' ' . $static . 'function ' . $entry->value->name . '(' . implode(', ', $params) . ')';
            if ($entry->value->returnType !== null) {
                $signature .= ': ' . $entry->value->returnType;
            }

            $consumer(sprintf("```php\n%s::%s\n```", $className, $signature));

            return;
        }
    }
}

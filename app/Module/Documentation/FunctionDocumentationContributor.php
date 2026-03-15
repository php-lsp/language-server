<?php

declare(strict_types=1);

namespace App\Module\Documentation;

use App\Core\Contracts\Documentation\AsDocumentationContributor;
use App\Core\Contracts\Documentation\DocumentationConsumer;
use App\Core\Contracts\Documentation\DocumentationContext;
use App\Core\Contracts\Documentation\DocumentationContributor;
use App\Module\Indexing\Indexer\FunctionIndexer;
use App\Module\Indexing\IndexLookup;
use App\Module\PsiFile\InMemoryPsiFileManager;
use App\Module\PsiFile\Tree;
use PhpParser\Node;

#[AsDocumentationContributor]
final class FunctionDocumentationContributor implements DocumentationContributor
{
    public function __construct(
        private readonly IndexLookup $indexLookup,
        private readonly InMemoryPsiFileManager $fileManager,
    ) {}

    public function contribute(DocumentationContext $context, DocumentationConsumer $consumer): void
    {
        $file = $this->fileManager->findPsiFile($context->editor, $context->textDocumentIdentifier);
        if ($file === null) {
            return;
        }

        $element = $file->findLastAtPosition($context->position);
        if (!$element instanceof Node\Name) {
            return;
        }

        $funcCall = Tree::parentOfType($element, Node\Expr\FuncCall::class);
        if ($funcCall === null) {
            return;
        }

        $funcName = $element->toString();

        foreach ($this->indexLookup->findByKey(FunctionIndexer::class) as $entry) {
            if ($entry->value->fqn !== $funcName) {
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

            $signature = 'function ' . $entry->value->fqn . '(' . implode(', ', $params) . ')';
            if ($entry->value->returnType !== null) {
                $signature .= ': ' . $entry->value->returnType;
            }

            $consumer(sprintf("```php\n%s\n```", $signature));

            return;
        }
    }
}

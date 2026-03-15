<?php

declare(strict_types=1);

namespace App\Module\Completion;

use App\Core\Contracts\Completion\AsCompletionContributor;
use App\Core\Contracts\Completion\CompletionConsumer;
use App\Core\Contracts\Completion\CompletionContext;
use App\Core\Contracts\Completion\CompletionContributor;
use App\Module\Indexing\Indexer\ClassMethodIndexer;
use App\Module\Indexing\Indexer\PropertyIndexer;
use App\Module\Indexing\IndexLookup;
use App\Module\PsiFile\Tree;
use Lsp\Protocol\Type\CompletionItem;
use Lsp\Protocol\Type\CompletionItemKind;
use Override;
use PhpParser\Node;

#[AsCompletionContributor]
final class ClassMemberCompletionContributor implements CompletionContributor
{
    public function __construct(
        private readonly IndexLookup $indexLookup,
    ) {}

    #[Override]
    public function contribute(CompletionContext $context, CompletionConsumer $consumer): void
    {
        $element = $context->currentNode();
        if ($element === null) {
            return;
        }

        $className = null;

        // $this->
        $propertyFetch = Tree::parentOfType($element, Node\Expr\PropertyFetch::class);
        if (
            $propertyFetch !== null
            && $propertyFetch->var instanceof Node\Expr\Variable
            && $propertyFetch->var->name === 'this'
        ) {
            $classNode = Tree::parentOfType($element, Node\Stmt\Class_::class);
            if ($classNode !== null) {
                $className = $classNode->namespacedName?->toString() ?? $classNode->name?->toString();
            }
        }

        // $this->method()
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

        // self:: or static::
        $staticCall = Tree::parentOfType($element, Node\Expr\StaticCall::class);
        if ($staticCall !== null && $staticCall->class instanceof Node\Name) {
            $name = $staticCall->class->toString();
            if ($name === 'self' || $name === 'static') {
                $classNode = Tree::parentOfType($element, Node\Stmt\Class_::class);
                if ($classNode !== null) {
                    $className = $classNode->namespacedName?->toString() ?? $classNode->name?->toString();
                }
            }
        }

        $staticPropertyFetch = Tree::parentOfType($element, Node\Expr\StaticPropertyFetch::class);
        if ($staticPropertyFetch !== null && $staticPropertyFetch->class instanceof Node\Name) {
            $name = $staticPropertyFetch->class->toString();
            if ($name === 'self' || $name === 'static') {
                $classNode = Tree::parentOfType($element, Node\Stmt\Class_::class);
                if ($classNode !== null) {
                    $className = $classNode->namespacedName?->toString() ?? $classNode->name?->toString();
                }
            }
        }

        if ($className === null) {
            return;
        }

        // Suggest methods
        foreach ($this->indexLookup->findByKey(ClassMethodIndexer::class) as $entry) {
            if ($entry->value->className !== $className) {
                continue;
            }

            $params = [];
            foreach ($entry->value->parameters as $param) {
                $paramStr = '';
                if ($param->type !== null) {
                    $paramStr .= $param->type . ' ';
                }
                $paramStr .= '$' . $param->name;
                $params[] = $paramStr;
            }

            $detail = $entry->value->visibility . ' function(' . implode(', ', $params) . ')';
            if ($entry->value->returnType !== null) {
                $detail .= ': ' . $entry->value->returnType;
            }

            $consumer(new CompletionItem(
                label: $entry->value->name,
                kind: CompletionItemKind::MethodKind,
                detail: $detail,
            ));
        }

        // Suggest properties
        foreach ($this->indexLookup->findByKey(PropertyIndexer::class) as $entry) {
            if ($entry->value->className !== $className) {
                continue;
            }

            $detail = $entry->value->visibility;
            if ($entry->value->type !== null) {
                $detail .= ' ' . $entry->value->type;
            }

            $consumer(new CompletionItem(
                label: $entry->value->name,
                kind: CompletionItemKind::PropertyKind,
                detail: $detail,
            ));
        }
    }
}

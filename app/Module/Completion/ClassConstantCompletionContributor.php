<?php

declare(strict_types=1);

namespace App\Module\Completion;

use App\Core\Contracts\Completion\AsCompletionContributor;
use App\Core\Contracts\Completion\CompletionConsumer;
use App\Core\Contracts\Completion\CompletionContext;
use App\Core\Contracts\Completion\CompletionContributor;
use App\Module\Indexing\Indexer\ClassConstantIndexer;
use App\Module\Indexing\IndexLookup;
use App\Module\PsiFile\Tree;
use Lsp\Protocol\Type\CompletionItem;
use Lsp\Protocol\Type\CompletionItemKind;
use Override;
use PhpParser\Node;

#[AsCompletionContributor]
final class ClassConstantCompletionContributor implements CompletionContributor
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

        // ClassName::CONST
        $constFetch = Tree::parentOfType($element, Node\Expr\ClassConstFetch::class);
        if ($constFetch !== null && $constFetch->class instanceof Node\Name) {
            $name = $constFetch->class->toString();
            if ($name === 'self' || $name === 'static') {
                $classNode = Tree::parentOfType($element, Node\Stmt\Class_::class);
                if ($classNode !== null) {
                    $className = $classNode->namespacedName?->toString() ?? $classNode->name?->toString();
                }
            }

            if ($name !== 'self' && $name !== 'static') {
                $className = $name;
            }
        }

        if ($className === null) {
            return;
        }

        foreach ($this->indexLookup->findByKey(ClassConstantIndexer::class) as $entry) {
            if ($entry->value->ownerFqn !== $className) {
                continue;
            }

            $detail = 'const';
            if ($entry->value->type !== null) {
                $detail .= ' ' . $entry->value->type;
            }
            if ($entry->value->value !== null) {
                $detail .= ' = ' . $entry->value->value;
            }

            $consumer(new CompletionItem(
                label: $entry->value->name,
                kind: CompletionItemKind::ConstantKind,
                detail: $detail,
            ));
        }
    }
}

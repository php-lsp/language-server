<?php

declare(strict_types=1);

namespace App\Module\Declaration;

use App\Core\Contracts\Declaration\AsDeclarationContributor;
use App\Core\Contracts\Declaration\DeclarationConsumer;
use App\Core\Contracts\Declaration\DeclarationContext;
use App\Core\Contracts\Declaration\DeclarationContributor;
use App\Module\Indexing\Indexer\ClassIndexer;
use App\Module\Indexing\Indexer\InterfaceIndexer;
use App\Module\Indexing\Indexer\TraitIndexer;
use App\Module\Indexing\IndexLookup;
use App\Module\PsiFile\InMemoryPsiFileManager;
use App\Module\PsiFile\Tree;
use Lsp\Protocol\Type\Location;
use Lsp\Protocol\Type\Position;
use Lsp\Protocol\Type\Range;
use Override;
use PhpParser\Node;

#[AsDeclarationContributor]
final class ClassDeclarationContributor implements DeclarationContributor
{
    public function __construct(
        private readonly IndexLookup $indexLookup,
        private readonly InMemoryPsiFileManager $fileManager,
    ) {}

    #[Override]
    public function contribute(DeclarationContext $context, DeclarationConsumer $consumer): void
    {
        $editor = $context->editor;
        $file = $this->fileManager->findPsiFile($editor, $context->textDocumentIdentifier);
        if ($file === null) {
            return;
        }

        $element = $file->findLastAtPosition($context->position);
        if (!$element instanceof Node\Name\FullyQualified) {
            return;
        }

        $className = $this->resolveClassName($element);
        if ($className === null) {
            return;
        }

        $zeroPosition = new Position(0, 0);
        $defaultRange = new Range($zeroPosition, $zeroPosition);

        foreach ($this->indexLookup->findByField(ClassIndexer::class, 'fqn', $className) as $value) {
            $consumer(new Location(
                uri: $value->uri,
                range: $defaultRange,
            ));
        }

        foreach ($this->indexLookup->findByField(InterfaceIndexer::class, 'fqn', $className) as $value) {
            $consumer(new Location(
                uri: $value->uri,
                range: $defaultRange,
            ));
        }

        foreach ($this->indexLookup->findByField(TraitIndexer::class, 'fqn', $className) as $value) {
            $consumer(new Location(
                uri: $value->uri,
                range: $defaultRange,
            ));
        }
    }

    private function resolveClassName(Node\Name\FullyQualified $element): ?string
    {
        $node = Tree::parentOfType($element, Node\Expr\ClassConstFetch::class);
        if ($node !== null) {
            return $node->class->toString();
        }

        $node = Tree::parentOfType($element, Node\Expr\New_::class);
        if ($node !== null) {
            return $node->class->toString();
        }

        $node = Tree::parentOfType($element, Node\Param::class);
        if ($node !== null) {
            return $node->type->toString();
        }

        $node = Tree::parentOfType($element, Node\UseItem::class);
        if ($node !== null) {
            return $node->name->toString();
        }

        $node = Tree::parentOfType($element, Node\Stmt\ClassMethod::class);
        if ($node !== null && $node->returnType === $element) {
            return $node->returnType->toString();
        }

        $node = Tree::parentOfType($element, Node\Stmt\Class_::class);
        if ($node !== null) {
            if ($node->extends === $element) {
                return $node->extends->toString();
            }
            if (in_array($element, $node->implements, strict: true)) {
                return $element->toString();
            }
        }

        $node = Tree::parentOfType($element, Node\Stmt\Interface_::class);
        if ($node !== null && in_array($element, $node->extends, strict: true)) {
            return $element->toString();
        }

        $node = Tree::parentOfType($element, Node\Stmt\TraitUse::class);
        if ($node !== null && in_array($element, $node->traits, strict: true)) {
            return $element->toString();
        }

        return null;
    }
}

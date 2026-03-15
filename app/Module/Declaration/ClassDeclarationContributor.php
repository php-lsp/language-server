<?php

declare(strict_types=1);

namespace App\Module\Declaration;

use App\Core\Contracts\Declaration\AsDeclarationContributor;
use App\Core\Contracts\Declaration\DeclarationConsumer;
use App\Core\Contracts\Declaration\DeclarationContext;
use App\Core\Contracts\Declaration\DeclarationContributor;
use App\Module\Indexing\Data\ClassData;
use App\Module\Indexing\Data\InterfaceData;
use App\Module\Indexing\Data\TraitData;
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

        $className = null;
        if ($node = Tree::parentOfType($element, Node\Expr\ClassConstFetch::class)) {
            $className = $node->class->toString();
        } elseif ($node = Tree::parentOfType($element, Node\Expr\New_::class)) {
            $className = $node->class->toString();
        } elseif ($node = Tree::parentOfType($element, Node\Param::class)) {
            $className = $node->type->toString();
        } elseif ($node = Tree::parentOfType($element, Node\UseItem::class)) {
            $className = $node->name->toString();
        } elseif ($node = Tree::parentOfType($element, Node\Stmt\ClassMethod::class)) {
            if ($node->returnType === $element) {
                $className = $node->returnType->toString();
            }
        } elseif ($node = Tree::parentOfType($element, Node\Stmt\Class_::class)) {
            if ($node->extends === $element) {
                $className = $node->extends->toString();
            } elseif (in_array($element, $node->implements, strict: true)) {
                $className = $element->toString();
            }
        } elseif ($node = Tree::parentOfType($element, Node\Stmt\Interface_::class)) {
            if (in_array($element, $node->extends, strict: true)) {
                $className = $element->toString();
            }
        } elseif ($node = Tree::parentOfType($element, Node\Stmt\TraitUse::class)) {
            if (in_array($element, $node->traits, strict: true)) {
                $className = $element->toString();
            }
        }

        if ($className === null) {
            return;
        }

        $zeroPosition = new Position(0, 0);
        $defaultRange = new Range($zeroPosition, $zeroPosition);

        foreach ($this->indexLookup->findByKey(ClassIndexer::class) as $value) {
            /** @var ClassData $data */
            $data = $value->value;
            if ($data->fqn !== $className) {
                continue;
            }
            $consumer(new Location(
                uri: $value->uri,
                range: $defaultRange,
            ));
        }

        foreach ($this->indexLookup->findByKey(InterfaceIndexer::class) as $value) {
            /** @var InterfaceData $data */
            $data = $value->value;
            if ($data->fqn !== $className) {
                continue;
            }
            $consumer(new Location(
                uri: $value->uri,
                range: $defaultRange,
            ));
        }

        foreach ($this->indexLookup->findByKey(TraitIndexer::class) as $value) {
            /** @var TraitData $data */
            $data = $value->value;
            if ($data->fqn !== $className) {
                continue;
            }
            $consumer(new Location(
                uri: $value->uri,
                range: $defaultRange,
            ));
        }
    }
}

<?php

declare(strict_types=1);

namespace App\Module\TypeDefinition;

use App\Core\Contracts\TypeDefinition\AsTypeDefinitionContributor;
use App\Core\Contracts\TypeDefinition\TypeDefinitionConsumer;
use App\Core\Contracts\TypeDefinition\TypeDefinitionContext;
use App\Core\Contracts\TypeDefinition\TypeDefinitionContributor;
use App\Module\Document\DocumentIdentifierFactoryInterface;
use App\Module\Indexing\Indexer\ClassIndexer;
use App\Module\Indexing\Indexer\EnumIndexer;
use App\Module\Indexing\Indexer\InterfaceIndexer;
use App\Module\Indexing\IndexLookup;
use App\Module\PsiFile\InMemoryPsiFileManager;
use App\Module\PsiFile\Tree;
use App\Module\TypeSystem\TypeResolverInterface;
use Lsp\Extension\DocumentManager\Editor\EditorInterface;
use Lsp\Protocol\Type\Location;
use Lsp\Protocol\Type\Position;
use Lsp\Protocol\Type\Range;
use Override;
use PHPStan\Type\ObjectType;
use PHPStan\Type\UnionType;

#[AsTypeDefinitionContributor]
final class TypeDefinitionTypeContributor implements TypeDefinitionContributor
{
    public function __construct(
        private readonly TypeResolverInterface $typeResolver,
        private readonly IndexLookup $indexLookup,
        private readonly InMemoryPsiFileManager $fileManager,
        private readonly DocumentIdentifierFactoryInterface $documentIdentifierFactory,
    ) {}

    #[Override]
    public function contribute(TypeDefinitionContext $context, TypeDefinitionConsumer $consumer): void
    {
        $typeResult = $this->typeResolver->resolveAtPosition(
            $context->editor,
            $context->textDocumentIdentifier,
            $context->position,
        );

        if ($typeResult === null) {
            return;
        }

        $classNames = $this->extractClassNames($typeResult->type);

        foreach ($classNames as $className) {
            $this->findTypeDeclaration($className, $context->editor, $consumer);
        }
    }

    /**
     * @return list<string>
     */
    private function extractClassNames(\PHPStan\Type\Type $type): array
    {
        $classNames = [];

        if ($type instanceof ObjectType) {
            $classNames[] = $type->getClassName();
        } elseif ($type instanceof UnionType) {
            foreach ($type->getTypes() as $innerType) {
                if (!$innerType instanceof ObjectType) {
                    continue;
                }

                $classNames[] = $innerType->getClassName();
            }
        }

        return $classNames;
    }

    private function findTypeDeclaration(
        string $className,
        EditorInterface $editor,
        TypeDefinitionConsumer $consumer,
    ): void {
        $indexers = [ClassIndexer::class, InterfaceIndexer::class, EnumIndexer::class];

        foreach ($indexers as $indexerClass) {
            foreach ($this->indexLookup->findByKey($indexerClass) as $entry) {
                if ($entry->value->fqn !== $className) {
                    continue;
                }

                $range = $this->resolveRange($entry->uri, $entry->value->startPosition, $editor);
                $consumer(new Location(uri: $entry->uri, range: $range));

                return;
            }
        }
    }

    private function resolveRange(string $uri, int $startPosition, EditorInterface $editor): Range
    {
        $textDocumentIdentifier = $this->documentIdentifierFactory->create($uri);
        $source = $this->fileManager->findPsiFile($editor, $textDocumentIdentifier);
        if ($source !== null) {
            [$line, $column] = Tree::toLineColumn($source->ast->document, $startPosition);
            $position = new Position($line, $column);

            return new Range($position, $position);
        }

        $zeroPosition = new Position(0, 0);

        return new Range($zeroPosition, $zeroPosition);
    }
}

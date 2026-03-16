<?php

declare(strict_types=1);

namespace App\Module\TypeDefinition;

use App\Core\Contracts\TypeDefinition\AsTypeDefinitionContributor;
use App\Core\Contracts\TypeDefinition\TypeDefinitionConsumer;
use App\Core\Contracts\TypeDefinition\TypeDefinitionContext;
use App\Core\Contracts\TypeDefinition\TypeDefinitionContributor;
use App\Module\Indexing\Indexer\ClassIndexer;
use App\Module\Indexing\Indexer\EnumIndexer;
use App\Module\Indexing\Indexer\InterfaceIndexer;
use App\Module\Indexing\IndexLookup;
use App\Module\PsiFile\PositionResolver;
use App\Module\TypeSystem\TypeResolverInterface;
use Lsp\Extension\DocumentManager\Editor\EditorInterface;
use Lsp\Protocol\Type\Location;
use Override;
use PHPStan\Type\ObjectType;
use PHPStan\Type\UnionType;

#[AsTypeDefinitionContributor]
final class TypeDefinitionTypeContributor implements TypeDefinitionContributor
{
    public function __construct(
        private readonly TypeResolverInterface $typeResolver,
        private readonly IndexLookup $indexLookup,
        private readonly PositionResolver $positionResolver,
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
            foreach ($this->indexLookup->findByField($indexerClass, 'fqn', $className) as $entry) {
                $range = $this->positionResolver->resolveRange($entry->uri, $entry->value->startPosition, $editor);
                $consumer(new Location(uri: $entry->uri, range: $range));

                return;
            }
        }
    }
}

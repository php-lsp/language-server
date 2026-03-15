<?php

declare(strict_types=1);

namespace App\Module\TypeDefinition;

use App\Core\Contracts\TypeDefinition\AsTypeDefinitionContributor;
use App\Core\Contracts\TypeDefinition\TypeDefinitionConsumer;
use App\Core\Contracts\TypeDefinition\TypeDefinitionContext;
use App\Core\Contracts\TypeDefinition\TypeDefinitionContributor;
use App\Module\Indexing\Data\ClassData;
use App\Module\Indexing\Data\EnumData;
use App\Module\Indexing\Data\InterfaceData;
use App\Module\Indexing\Indexer\ClassIndexer;
use App\Module\Indexing\Indexer\EnumIndexer;
use App\Module\Indexing\Indexer\InterfaceIndexer;
use App\Module\Indexing\IndexLookup;
use App\Module\TypeSystem\TypeResolverInterface;
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
            $this->findTypeDeclaration($className, $consumer);
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

    private function findTypeDeclaration(string $className, TypeDefinitionConsumer $consumer): void
    {
        $zeroPosition = new Position(0, 0);
        $defaultRange = new Range($zeroPosition, $zeroPosition);

        foreach ($this->indexLookup->findByKey(ClassIndexer::class) as $entry) {
            /** @var ClassData $data */
            $data = $entry->value;
            if ($data->fqn === $className) {
                $consumer(new Location(uri: $entry->uri, range: $defaultRange));

                return;
            }
        }

        foreach ($this->indexLookup->findByKey(InterfaceIndexer::class) as $entry) {
            /** @var InterfaceData $data */
            $data = $entry->value;
            if ($data->fqn === $className) {
                $consumer(new Location(uri: $entry->uri, range: $defaultRange));

                return;
            }
        }

        foreach ($this->indexLookup->findByKey(EnumIndexer::class) as $entry) {
            /** @var EnumData $data */
            $data = $entry->value;
            if ($data->fqn === $className) {
                $consumer(new Location(uri: $entry->uri, range: $defaultRange));

                return;
            }
        }
    }
}

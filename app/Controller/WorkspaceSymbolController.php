<?php

declare(strict_types=1);

namespace App\Controller;

use App\Core\Contracts\PrefixMatcher\StrContainsMatcher;
use App\Module\Indexing\Indexer\ClassIndexer;
use App\Module\Indexing\Indexer\ClassMethodIndexer;
use App\Module\Indexing\Indexer\EnumIndexer;
use App\Module\Indexing\Indexer\FunctionIndexer;
use App\Module\Indexing\Indexer\GlobalConstantIndexer;
use App\Module\Indexing\Indexer\InterfaceIndexer;
use App\Module\Indexing\Indexer\PropertyIndexer;
use App\Module\Indexing\Indexer\TraitIndexer;
use App\Module\Indexing\IndexLookup;
use Lsp\Kernel\Attribute\AsController;
use Lsp\Protocol\Type\Location;
use Lsp\Protocol\Type\Position;
use Lsp\Protocol\Type\Range;
use Lsp\Protocol\Type\SymbolInformation;
use Lsp\Protocol\Type\SymbolKind;
use Lsp\Protocol\Type\WorkspaceSymbolParams;
use Lsp\Router\Attribute\Route;

#[AsController, Route('workspace/symbol')]
final class WorkspaceSymbolController
{
    public function __construct(
        private readonly IndexLookup $indexLookup,
    ) {}

    /**
     * @return list<SymbolInformation>
     */
    public function __invoke(WorkspaceSymbolParams $params): array
    {
        $query = $params->query;
        $matcher = new StrContainsMatcher($query);
        $symbols = [];
        $zeroPosition = new Position(0, 0);
        $defaultRange = new Range($zeroPosition, $zeroPosition);

        foreach ($this->indexLookup->findByKey(ClassIndexer::class) as $entry) {
            if ($query !== '' && !$matcher->match($entry->value->fqn)) {
                continue;
            }
            $symbols[] = new SymbolInformation(
                location: new Location(uri: $entry->uri, range: $defaultRange),
                name: $entry->value->fqn,
                kind: SymbolKind::ClassKind,
            );
        }

        foreach ($this->indexLookup->findByKey(InterfaceIndexer::class) as $entry) {
            if ($query !== '' && !$matcher->match($entry->value->fqn)) {
                continue;
            }
            $symbols[] = new SymbolInformation(
                location: new Location(uri: $entry->uri, range: $defaultRange),
                name: $entry->value->fqn,
                kind: SymbolKind::InterfaceKind,
            );
        }

        foreach ($this->indexLookup->findByKey(TraitIndexer::class) as $entry) {
            if ($query !== '' && !$matcher->match($entry->value->fqn)) {
                continue;
            }
            $symbols[] = new SymbolInformation(
                location: new Location(uri: $entry->uri, range: $defaultRange),
                name: $entry->value->fqn,
                kind: SymbolKind::ClassKind,
                containerName: 'trait',
            );
        }

        foreach ($this->indexLookup->findByKey(EnumIndexer::class) as $entry) {
            if ($query !== '' && !$matcher->match($entry->value->fqn)) {
                continue;
            }
            $symbols[] = new SymbolInformation(
                location: new Location(uri: $entry->uri, range: $defaultRange),
                name: $entry->value->fqn,
                kind: SymbolKind::EnumKind,
            );
        }

        foreach ($this->indexLookup->findByKey(FunctionIndexer::class) as $entry) {
            if ($query !== '' && !$matcher->match($entry->value->fqn)) {
                continue;
            }
            $symbols[] = new SymbolInformation(
                location: new Location(uri: $entry->uri, range: $defaultRange),
                name: $entry->value->fqn,
                kind: SymbolKind::FunctionKind,
            );
        }

        foreach ($this->indexLookup->findByKey(ClassMethodIndexer::class) as $entry) {
            if ($query !== '' && !$matcher->match($entry->value->name)) {
                continue;
            }
            $symbols[] = new SymbolInformation(
                location: new Location(uri: $entry->uri, range: $defaultRange),
                name: $entry->value->name,
                kind: SymbolKind::MethodKind,
                containerName: $entry->value->className,
            );
        }

        foreach ($this->indexLookup->findByKey(PropertyIndexer::class) as $entry) {
            if ($query !== '' && !$matcher->match($entry->value->name)) {
                continue;
            }
            $symbols[] = new SymbolInformation(
                location: new Location(uri: $entry->uri, range: $defaultRange),
                name: '$' . $entry->value->name,
                kind: SymbolKind::PropertyKind,
                containerName: $entry->value->className,
            );
        }

        foreach ($this->indexLookup->findByKey(GlobalConstantIndexer::class) as $entry) {
            if ($query !== '' && !$matcher->match($entry->value->name)) {
                continue;
            }
            $symbols[] = new SymbolInformation(
                location: new Location(uri: $entry->uri, range: $defaultRange),
                name: $entry->value->name,
                kind: SymbolKind::ConstantKind,
            );
        }

        return $symbols;
    }
}

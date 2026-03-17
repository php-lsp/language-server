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
use App\Module\Indexing\Storage\Entry;
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

        foreach ($this->getSources() as [$indexerClass, $kind, $nameExtractor, $containerExtractor]) {
            foreach ($this->indexLookup->findByKey($indexerClass) as $entry) {
                $name = $nameExtractor($entry);
                if ($query !== '' && !$matcher->match($name)) {
                    continue;
                }
                $symbols[] = new SymbolInformation(
                    location: new Location(uri: $entry->uri, range: $defaultRange),
                    name: $name,
                    kind: $kind,
                    containerName: $containerExtractor !== null ? $containerExtractor($entry) : null,
                );
            }
        }

        return $symbols;
    }

    /**
     * @return list<array{class-string, SymbolKind, \Closure(Entry): string, (\Closure(Entry): ?string)|null}>
     */
    private function getSources(): array
    {
        $fqn = static fn(Entry $e): string => $e->value->fqn;
        $name = static fn(Entry $e): string => $e->value->name;

        return [
            [ClassIndexer::class, SymbolKind::ClassKind, $fqn, null],
            [InterfaceIndexer::class, SymbolKind::InterfaceKind, $fqn, null],
            [TraitIndexer::class, SymbolKind::ClassKind, $fqn, static fn(Entry $e): string => 'trait'],
            [EnumIndexer::class, SymbolKind::EnumKind, $fqn, null],
            [FunctionIndexer::class, SymbolKind::FunctionKind, $fqn, null],
            [
                ClassMethodIndexer::class,
                SymbolKind::MethodKind,
                $name,
                static fn(Entry $e): string => $e->value->className,
            ],
            [
                PropertyIndexer::class,
                SymbolKind::PropertyKind,
                static fn(Entry $e): string => '$' . $e->value->name,
                static fn(Entry $e): string => $e->value->className,
            ],
            [GlobalConstantIndexer::class, SymbolKind::ConstantKind, $name, null],
        ];
    }
}

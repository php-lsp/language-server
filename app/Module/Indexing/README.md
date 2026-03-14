# Indexing Module

Background indexing system that traverses project files on open and builds
a structured index for fast symbol lookups. Provides the data foundation
for completion, declaration, references, and other LSP features.

## Architecture

```
Project Files
    |
    v
Indexer (orchestrator) ── recursively walks project files + loads PHP stubs
    |
    v
IndexerInterface[] (individual indexers)
    |
    v
StorageInterface (InMemoryStorage)
    |
    v
IndexLookup ── used by contributors for queries
```

## Core Classes

| Class | Description |
|-------|-------------|
| `Indexer` | Orchestrator — walks project files, dispatches to individual indexers, loads PHP stubs |
| `IndexLookup` | Query interface — contributors use `findByKey()` to search the index |

## Declaration Indexers (`Indexer/`)

| Class | Key | Indexed Data |
|-------|-----|-------------|
| `ClassIndexer` | `php.classes.fqn` | FQN → FQN (string) |
| `InterfaceIndexer` | `php.interfaces.fqn` | FQN (list) |
| `TraitIndexer` | `php.traits.fqn` | FQN (list) |
| `FunctionIndexer` | `php.functions.fqn` | FQN → [name, startPos] |
| `ClassMethodIndexer` | `php.classMethods.fqn` | ClassName → list of method names |

## Usage Indexers (`Indexer/`)

| Class | Key | Indexed Data |
|-------|-----|-------------|
| `ClassUsageIndexer` | `php.usages.classes` | Class usage locations |
| `MethodCallUsageIndexer` | `php.usages.methodCalls` | Method call locations |
| `FunctionCallUsageIndexer` | `php.usages.functionCalls` | Function call locations |
| `PropertyAccessUsageIndexer` | `php.usages.propertyAccess` | Property access locations |
| `ClassConstantUsageIndexer` | `php.usages.classConstants` | Class constant usage locations |

## Base Class

| Class | Description |
|-------|-------------|
| `AbstractPhpIndexer` | Base class for PHP indexers — handles file parsing via `PHPPsiFileParser` and delegates to `indexInternal()` |

## Storage (`Storage/`)

| Class | Description |
|-------|-------------|
| `StorageInterface` | Contract for index storage backends |
| `InMemoryStorage` | In-memory implementation — stores `Entry` objects keyed by indexer key |
| `Entry` | Value object wrapping an indexed item (key + value) |
| `SerializerInterface` | Contract for serialization of indexed data |
| `JsonSerializer` | JSON-based serializer implementation |

## Contract

- **Interface:** `App\Core\Contracts\Indexing\IndexerInterface<TValue>`
- **Attribute:** `#[AsIndexer]`
- **DI Tag:** `lsp.indexers`
- **Methods:** `getKey(): string` (static), `supports(VirtualFileInterface): bool`, `index(VirtualFileInterface): iterable<TValue>`

## Querying the Index

Contributors query the index via `IndexLookup`:

```php
$this->indexLookup->findByKey(ClassIndexer::class)
```

## Adding a New Indexer

```php
#[AsIndexer]
class MyIndexer extends AbstractPhpIndexer
{
    public static function getKey(): string
    {
        return 'php.my.key';
    }

    protected function indexInternal(PHPPsiFile $phpFile): array
    {
        // ... extract data from AST ...
        return ['key' => 'value'];
    }
}
```

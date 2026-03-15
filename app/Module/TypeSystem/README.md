# TypeSystem Module

PHPStan-based type resolution system. Provides type inference at cursor
position by leveraging PHPStan's `NodeScopeResolver` and `ScopeFactory`.

## Classes

| Class | Description |
|-------|-------------|
| `TypeResolverInterface` | Contract for type resolution — `resolveAtPosition()`, `resolveVariableAtPosition()` |
| `TypeResolver` | Implementation — parses AST, runs PHPStan scope resolution, finds type of the node at cursor |
| `TypeResult` | Value object wrapping PHPStan `Type` + `Scope` + `Node`, with `describe()` / `describeShort()` |
| `PHPStanBootstrap` | Boots PHPStan DI container lazily, provides `NodeScopeResolver`, `ScopeFactory`, `ReflectionProvider` |
| `UriHelper` | Converts `file://` URIs to filesystem paths (handles URL encoding, Windows drive letters) |

## Architecture

```
TypeResolverInterface
    |
    v
TypeResolver
    |-- uses InMemoryPsiFileManager to get AST
    |-- uses PHPStanBootstrap to get NodeScopeResolver + ScopeFactory
    |-- walks AST with PHPStan scope resolution
    |-- finds matching Expr node at target position
    |
    v
TypeResult (Type + Scope + Node)
```

## Key Methods

### TypeResolverInterface

- `resolveAtPosition(editor, textDocumentIdentifier, position): ?TypeResult` — resolves
  the type of the expression at the given cursor position
- `resolveVariableAtPosition(editor, textDocumentIdentifier, position, variableName): ?Type` —
  resolves the type of a specific variable at a given position

### PHPStanBootstrap

- `getNodeScopeResolver(): NodeScopeResolver` — provides PHPStan's scope resolver
- `getScopeFactory(): ScopeFactory` — provides scope creation
- `getReflectionProvider(): ReflectionProvider` — provides class/function reflection
- `createScopeForFile(filePath): MutatingScope` — creates initial scope for a file
- `setAnalysedPaths(paths)` — sets analysed paths (invalidates container on change)

## Usage

Used by `DocblockDocumentationContributor` for hover type display:

```php
$result = $this->typeResolver->resolveAtPosition($editor, $textDocumentId, $position);
if ($result !== null) {
    $consumer(sprintf("```php\n%s\n```", $result->describeShort()));
}
```

## Dependencies

- `phpstan/phpstan` — type inference engine
- `InMemoryPsiFileManager` — AST access
- `ProjectManager` — project path for PHPStan container bootstrap

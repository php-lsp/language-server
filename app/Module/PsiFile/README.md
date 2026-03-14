# PsiFile Module

PSI (Program Structure Interface) — an abstraction layer over PHP file ASTs,
built on `nikic/php-parser`. Provides parsing, caching, and position-based
node lookup for all LSP features that need AST access.

## Classes

| Class | Description |
|-------|-------------|
| `PHPPsiFileParser` | Wraps `nikic/php-parser` — parses PHP source code into a `SourceFileRoot` |
| `SourceFileRoot` | AST root node + metadata (parse errors, associated document) |
| `PHPPsiFile` | Wrapper over the AST with position-based lookup methods |
| `InMemoryPsiFileManager` | Manages a FIFO cache of parsed files with version-based invalidation |
| `FifoCache` | Generic FIFO cache implementation (capacity: 300 files) |
| `Tree` | Static utility class for AST operations (find children by type, node-to-string) |

## Architecture

```
PHP Source Code
    |
    v
PHPPsiFileParser  --> parses via nikic/php-parser
    |
    v
SourceFileRoot    --> AST root + metadata (errors, document)
    |
    v
PHPPsiFile        --> wrapper with position-based lookup methods
    |
    v
InMemoryPsiFileManager --> caching + invalidation by document version
```

## Key Methods

### PHPPsiFile

- `findAtPosition(Position)` — returns all nodes containing the given position
- `findLastAtPosition(Position)` — returns the deepest (most specific) node at a position

### InMemoryPsiFileManager

- Maintains a FIFO cache of up to 300 parsed files
- Automatically invalidates cache entries when document version changes
- On parse errors — sends diagnostics to the client via `textDocument/publishDiagnostics`

### Tree

- `childrenOfType(array $nodes, string $class)` — find child nodes by type
- `toString(?Node $node)` — convert AST node to string representation

## Usage

Controllers and contributors access the AST via `InMemoryPsiFileManager`:

```php
$psiFile = $this->fileManager->get($editor, $textDocumentIdentifier);
$node = $psiFile->findLastAtPosition($position);
```

`CompletionContext` also provides a convenience method:

```php
$node = $context->currentNode(); // shortcut for findLastAtPosition
```

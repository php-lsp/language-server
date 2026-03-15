# Position Systems: LSP Protocol vs php-parser

This document describes the position conventions used by the LSP protocol
and nikic/php-parser, and how the codebase converts between them.

## Position Conventions

| System | Lines | Characters | File offsets |
|--------|-------|-----------|--------------|
| **LSP Protocol** | 0-based | 0-based | not used |
| **php-parser `getStartLine()`** | 1-based | not available | not used |
| **php-parser `getStartFilePos()`** | not used | not used | 0-based byte offset |

### LSP Protocol (`Lsp\Protocol\Type\Position`)

- `line`: **0-based** (first line is 0)
- `character`: **0-based** (first character is 0)
- Character offsets use **UTF-16 code units** by default (negotiable since LSP 3.17)

### nikic/php-parser (`PhpParser\Node`)

- `getStartLine()` / `getEndLine()`: **1-based** (first line is 1, returns -1 if unavailable)
- `getStartFilePos()` / `getEndFilePos()`: **0-based byte offsets** from the beginning of the file
- There is no built-in column/character method — column must be computed from byte offset

## Converting php-parser to LSP

### Line: subtract 1

```php
$lspLine = $node->getStartLine() - 1;
```

### File position to line + column: use `Tree` helpers

```php
// Single node range (most common)
$range = Tree::getRange($node, $psiFile);

// Byte offset to [line, column] (0-based both)
[$line, $column] = Tree::toLineColumn($document, $node->getStartFilePos());
```

### Converting LSP position to php-parser line

```php
$phpParserLine = $position->line + 1;
```

## Conversion Functions

### `Tree::getRange(Node, PHPPsiFile): Range`

Converts a php-parser node into an LSP `Range`. Internally:

- Start line: `getStartLine() - 1`
- Start column: `toColumn(document, getStartFilePos() - 1)`
- End line: `getEndLine() - 1`
- End column: `toColumn(document, getEndFilePos())`

### `Tree::toLineColumn(Document, int $pos): array{int, int}`

Converts a byte offset to 0-based `[line, column]` using cached line offsets
and binary search. Used by declaration/reference contributors to convert
stored byte offsets back to LSP positions.

### `Tree::toColumn(Document, int $pos): int`

Converts a byte offset to a column number by finding the last newline before
the position.

### `PHPPsiFile::toColumn(Document, int $pos): int`

Same logic as `Tree::toColumn`, used internally for position matching in
`findAtPosition()`.

## Where Conversions Happen

| Location | Direction | Method |
|----------|-----------|--------|
| `Tree::getRange()` | php-parser -> LSP | Line subtraction + column computation |
| `PHPPsiFile::findAtPosition()` | LSP -> php-parser | `$position->line + 1` for line comparison |
| `InMemoryPsiFileManager::refreshFile()` | php-parser -> LSP | Error positions for diagnostics |
| `DiagnosticController` | php-parser -> LSP | Parse error line conversion |
| Declaration contributors | byte offset -> LSP | `Tree::toLineColumn()` on stored offsets |
| Reference contributors | byte offset -> LSP | `Tree::toLineColumn()` on stored offsets |
| Indexers (all) | php-parser -> storage | Store raw `getStartFilePos()` / `getEndFilePos()` |
| `TypeResolver` | LSP -> php-parser | `$position->line + 1` for scope matching |

## Indexing Storage

Indexers store raw byte offsets (`getStartFilePos()`, `getEndFilePos()`) in
`IndexData` value objects. These are converted to LSP positions on-the-fly
when building responses (e.g., `Location`, `Range`) via `Tree::toLineColumn()`.

## Common Pitfalls

1. **Off-by-one on lines**: Always remember `phpParserLine = lspLine + 1`.
   Forgetting the conversion produces positions shifted by one line.

2. **Byte offsets vs character offsets**: `getStartFilePos()` returns byte
   offsets, not character offsets. For ASCII files these are identical, but
   for UTF-8 files with multibyte characters they differ.

3. **Stale positions**: When a document is modified via incremental sync,
   the AST may still hold positions from the previous content. Byte offsets
   can exceed the current document length — always clamp or guard against this.

4. **Column computation**: There is no direct column method in php-parser.
   Column must be derived by finding the last newline before the byte offset.

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

All conversions are centralized in `Tree` (`app/Module/PsiFile/Tree.php`).
**Do not perform manual `+1` / `-1` arithmetic in controllers or contributors** —
use the helpers below instead.

### Line conversion helpers

```php
// php-parser 1-based → LSP 0-based
$lspLine = Tree::toLspLine($node->getStartLine());

// LSP 0-based → php-parser 1-based
$parserLine = Tree::toParserLine($position->line);

// Shorthand for node start/end lines (returns 0-based)
$startLine = Tree::nodeStartLine($node);
$endLine = Tree::nodeEndLine($node);
```

### Node to LSP Range

```php
// Single node range (most common)
$range = Tree::getRange($node, $psiFile);
```

### Byte offset to line + column

```php
// Byte offset to [line, column] (0-based both)
[$line, $column] = Tree::toLineColumn($document, $node->getStartFilePos());
```

### php-parser Error to LSP Range

```php
$range = Tree::errorRange($error, $document);
```

## Conversion Functions Reference

### `Tree::toLspLine(int $parserLine): int`

Converts a 1-based php-parser line to a 0-based LSP line. Equivalent to
`$parserLine - 1`.

### `Tree::toParserLine(int $lspLine): int`

Converts a 0-based LSP line to a 1-based php-parser line. Equivalent to
`$lspLine + 1`.

### `Tree::nodeStartLine(Node): int`

Returns the 0-based LSP start line of a php-parser node.

### `Tree::nodeEndLine(Node): int`

Returns the 0-based LSP end line of a php-parser node.

### `Tree::getRange(Node, PHPPsiFile): Range`

Converts a php-parser node into an LSP `Range`. Internally:

- Start line: `getStartLine() - 1`
- Start column: `toColumn(document, getStartFilePos() - 1)`
- End line: `getEndLine() - 1`
- End column: `toColumn(document, getEndFilePos())`

### `Tree::toLineColumn(Document, int $pos): array{int, int}`

Converts a byte offset to 0-based `[line, column]` using cached line offsets
and binary search. Used by declaration/reference/definition/implementation/
type-definition contributors to convert stored byte offsets back to LSP
positions.

### `Tree::toColumn(Document, int $pos): int`

Converts a byte offset to a column number by finding the last newline before
the position.

### `Tree::errorRange(Error, Document): Range`

Converts a php-parser `Error` object to an LSP `Range`, handling the 1-based
to 0-based line conversion and column extraction.

## Where Conversions Happen

All position conversions are centralized in `Tree`. Consumers should only
call `Tree` helpers and never perform manual arithmetic.

| Location | Direction | Method |
|----------|-----------|--------|
| `Tree::getRange()` | php-parser → LSP | Line subtraction + column computation |
| `Tree::toLspLine()` / `toParserLine()` | line conversion | Single arithmetic operation |
| `Tree::nodeStartLine()` / `nodeEndLine()` | php-parser → LSP | Node line helpers |
| `Tree::errorRange()` | php-parser Error → LSP | Error position conversion |
| `PHPPsiFile::findAtPosition()` | LSP → php-parser | Uses `Tree::toParserLine()` |
| `TypeResolver` | LSP → php-parser | Uses `Tree::toParserLine()` |
| Declaration/Reference/Definition contributors | byte offset → LSP | `Tree::toLineColumn()` on stored offsets |
| Implementation/TypeDefinition contributors | byte offset → LSP | `Tree::toLineColumn()` on stored offsets |
| Diagnostics (`DiagnosticController`, `InMemoryPsiFileManager`) | php-parser Error → LSP | `Tree::errorRange()` |
| Code action contributors | php-parser → LSP | `Tree::nodeStartLine()` / `Tree::nodeEndLine()` |
| Indexers (all) | php-parser → storage | Store raw `getStartFilePos()` / `getEndFilePos()` |

## Indexing Storage

Indexers store raw byte offsets (`getStartFilePos()`, `getEndFilePos()`) in
`IndexData` value objects. These are converted to LSP positions on-the-fly
when building responses (e.g., `Location`, `Range`) via `Tree::toLineColumn()`.

## Common Pitfalls

1. **Off-by-one on lines**: Always use `Tree::toLspLine()` / `Tree::toParserLine()`
   instead of manual `- 1` / `+ 1`. The helpers make intent clear and prevent
   forgetting the conversion.

2. **Byte offsets vs character offsets**: `getStartFilePos()` returns byte
   offsets, not character offsets. For ASCII files these are identical, but
   for UTF-8 files with multibyte characters they differ.

3. **Stale positions**: When a document is modified via incremental sync,
   the AST may still hold positions from the previous content. Byte offsets
   can exceed the current document length — always clamp or guard against this.

4. **Column computation**: There is no direct column method in php-parser.
   Column must be derived by finding the last newline before the byte offset.

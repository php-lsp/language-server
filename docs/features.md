# Feature Roadmap

This document tracks LSP capabilities — what is already implemented, what needs
fixing, and what to add next. Priorities are based on analysis of
[Intelephense](https://intelephense.com/),
[Phpactor](https://phpactor.readthedocs.io/en/master/lsp/support.html),
[PHPStorm](https://www.jetbrains.com/help/phpstorm/refactoring-source-code.html),
and the [LSP 3.18 specification](https://github.com/microsoft/language-server-protocol/blob/gh-pages/_specifications/lsp/3.18/specification.md).

---

## Currently Implemented

| LSP Method                        | Status      | Notes                                             |
|-----------------------------------|-------------|---------------------------------------------------|
| `initialize` / `initialized`     | Done        | Workspace folders, project indexing                |
| `$/setTrace`                      | Done        | Trace level setting                                |
| `textDocument/completion`         | Done        | 5 contributors, parallel execution, 1s timeout    |
| `textDocument/hover`              | Done        | 2 documentation contributors                      |
| `textDocument/declaration`        | Done        | 3 declaration contributors                        |
| `textDocument/references`         | Done        | 7 reference contributors                          |
| `textDocument/signatureHelp`      | Done        | 3 signature contributors                          |
| `textDocument/prepareRename`      | Done        | Returns range of symbol under cursor               |
| `textDocument/rename`             | **Stub**    | Controller exists but returns nothing              |
| `textDocument/diagnostic`         | Done        | PHP parse errors only                              |
| `textDocument/publishDiagnostics` | Done        | Push-model diagnostics                             |
| `textDocument/documentSymbol`     | **Disabled**| Code present, not registered (`#[AsController]`)   |

### Indexing System (10 indexers)

- **Declarations:** class, function, interface, trait, class method
- **Usages:** class, function call, method call, property access, class constant

---

## Roadmap

### Phase 1 — Fix & Enable Existing Code

These are low-hanging fruit — code already exists but is incomplete or disabled.

- [ ] **`textDocument/rename`** — implement `WorkspaceEdit` creation from
  reference results. The controller already collects references, just needs to
  build the edit with `TextEdit` entries per document.
  - *Contributor pattern:* reuse existing `ReferenceContributor` list
  - *Implementation:* map each `Location` → `TextDocumentEdit` with new name
  - *Edge cases:* renaming classes should also rename the file (like Intelephense)
  - *Acceptance params type:* `RenameParams` (currently uses `PrepareRenameParams`)

- [ ] **`textDocument/documentSymbol`** — enable the existing controller.
  - *Steps:* add `#[AsController]` attribute, uncomment `documentSymbolProvider`
    in `InitializeController`, add interface/enum/trait/constant support,
    handle `null` from `findPsiFile`
  - *Stretch:* extract into contributor pattern for extensibility

- [ ] **Indexing on initialize** — `walkWorkspaceFolder` has `return;` before
  indexing. Re-enable with async/background execution so `initialize` doesn't
  block.

### Phase 2 — Core Features (Must-Have)

Essential features that every competitive PHP LSP provides.

- [ ] **`textDocument/definition`** (`definitionProvider`)
  Separate from `declaration`. In LSP, "definition" goes to the concrete
  implementation; "declaration" goes to the interface/abstract. Most editors
  bind `Ctrl+Click` / `F12` to `definition`, not `declaration`.
  - *Implementation:* add `DefinitionContributor` interface + attribute
  - *Reuse:* `DeclarationContributor` logic, plus resolving interfaces →
    concrete classes

- [ ] **`textDocument/typeDefinition`** (`typeDefinitionProvider`)
  Navigate to the type of a variable/parameter. E.g. clicking `$user` jumps to
  `class User`.
  - *Use `TypeResolver`* to get the type, then find declaration of that type

- [ ] **`textDocument/implementation`** (`implementationProvider`)
  Find all implementations of an interface or abstract class/method.
  - *Indexer work:* need `implements`/`extends` index
  - *Both Intelephense (premium) and Phpactor support this*

- [ ] **`textDocument/codeAction`** (`codeActionProvider`)
  Contextual actions at cursor position. Start with:
  - **Import symbol** — add missing `use` statement
  - **Implement interface methods** — generate method stubs
  - **Add PHPDoc** — auto-generate docblock from signature
  - **Remove unused import** — quick-fix for unused `use`
  - *Architecture:* `CodeActionContributor` interface with `#[AsCodeActionContributor]`

- [ ] **`textDocument/formatting`** (`documentFormattingProvider`)
  Format entire document. Delegate to external tool (Mago, php-cs-fixer, phpcbf).
  - *Implementation:* run formatter as subprocess, return `TextEdit[]`
  - *Config:* allow user to configure formatter command in workspace settings

- [ ] **`textDocument/rangeFormatting`** (`documentRangeFormattingProvider`)
  Format a selected range only. Same as above but with range parameter.

- [ ] **`textDocument/documentHighlight`** (`documentHighlightProvider`)
  Highlight all occurrences of symbol under cursor within the document.
  - *Implementation:* lightweight version of `references` scoped to current file
  - *Both Intelephense and Phpactor support this*

- [ ] **`workspace/symbol`** (`workspaceSymbolProvider`)
  Search for symbols across the entire workspace (Ctrl+T in most editors).
  - *Use existing index:* query `IndexLookup` by name pattern (fuzzy matching)
  - *Return:* `SymbolInformation[]` with location

### Phase 3 — Enhanced Navigation (Nice-to-Have)

Features that significantly improve developer experience.

- [ ] **`textDocument/foldingRange`** (`foldingRangeProvider`)
  Code folding for classes, methods, use-blocks, comments, arrays, heredocs.
  - *Implementation:* AST walk returning `FoldingRange[]` with `FoldingRangeKind`
  - *Intelephense premium feature*

- [ ] **`textDocument/selectionRange`** (`selectionRangeProvider`)
  Smart select — expand/shrink selection based on AST structure.
  - *Implementation:* at cursor position, build a chain of AST node ranges
    from innermost to outermost
  - *Phpactor and Intelephense premium support this*

- [ ] **`textDocument/inlayHint`** (`inlayHintProvider`)
  Inline type/parameter hints displayed in the editor. Show:
  - Parameter names at call sites: `foo(/* name: */ "bar")`
  - Inferred return types on closures
  - Variable types on assignments
  - *Use `TypeResolver`* for inferred types

- [ ] **`textDocument/codeLens`** (`codeLensProvider`)
  Actionable annotations above declarations. Show:
  - Reference count: `3 references`
  - Implementation count: `2 implementations`
  - Override indicator: `overrides Parent::method`
  - *Intelephense premium feature*

- [ ] **`workspace/didChangeWatchedFiles`**
  React to file system changes (create/rename/delete) outside the editor.
  Re-index affected files.

- [ ] **`textDocument/onTypeFormatting`** (`documentOnTypeFormattingProvider`)
  Auto-format on certain triggers (`;`, `}`, `\n`).

### Phase 4 — Advanced Features

- [ ] **`textDocument/semanticTokens`** (`semanticTokensProvider`)
  Rich syntax highlighting with semantic understanding. Token types:
  `class`, `interface`, `enum`, `function`, `method`, `property`, `variable`,
  `parameter`, `namespace`, `type`.
  - *Full and delta modes:* `semanticTokens/full` + `semanticTokens/full/delta`
  - *Requires token legend registration in `InitializeResult`*

- [ ] **`textDocument/linkedEditingRange`** (`linkedEditingRangeProvider`)
  Synchronized editing of related ranges (e.g. opening/closing XML tags,
  matching variable names in string interpolation).

- [ ] **`callHierarchy/incomingCalls` / `outgoingCalls`**
  Call hierarchy — who calls this function, and what does this function call.
  - *Requires:* `textDocument/prepareCallHierarchy` + the two direction methods
  - *PHPStorm equivalent:* "Call Hierarchy" (Ctrl+Alt+H)

- [ ] **`typeHierarchy/supertypes` / `subtypes`**
  Type hierarchy — visualize class inheritance tree.
  - *Requires:* `textDocument/prepareTypeHierarchy` + the two direction methods
  - *Intelephense premium feature*

- [ ] **Refactoring Code Actions**
  PHPStorm-grade refactoring exposed via `textDocument/codeAction`:
  - **Extract Method** — extract selection to new method
  - **Extract Variable** — extract expression to variable
  - **Extract Constant** — extract value to class constant
  - **Inline Variable** — inline a variable's value at usage sites
  - **Change Signature** — add/remove/reorder parameters
  - **Safe Delete** — remove symbol with usage check
  - **Move Class** — move class to different namespace/file

- [ ] **Enhanced Diagnostics**
  Extend beyond parse errors:
  - Undefined variable/function/class warnings
  - Unused variable/import warnings
  - Type mismatch warnings (via PHPStan integration)
  - PHPDoc type vs declared type inconsistency
  - Missing return statements
  - *Architecture:* `DiagnosticContributor` interface

---

## Implementation Notes

### Adding a New LSP Method

1. Create controller in `app/Controller/TextDocument/` with `#[AsController]`
   and `#[Route('textDocument/methodName')]`
2. Register capability in `InitializeController.php` → `ServerCapabilities`
3. If using contributor pattern:
   - Define interface in `app/Core/Contracts/NewFeature/`
   - Add registration attribute `#[AsNewFeatureContributor]`
   - Register DI tag in `Application.php`
   - Create context + consumer classes
4. Implement contributors in `app/Module/NewFeature/`
5. Add tests (unit + feature)
6. Update `docs/contributors.md` with new contributor type table entry

### Contributor Pattern Checklist (per feature)

```
app/Core/Contracts/{Feature}/
├── {Feature}Contributor.php          # Interface
├── As{Feature}Contributor.php        # Attribute
├── {Feature}Context.php              # Request context DTO
└── {Feature}Consumer.php             # Result collector

app/Module/{Feature}/
├── {Specific}{Feature}Contributor.php  # Implementation(s)

app/Controller/TextDocument/
└── {Feature}Controller.php           # Route handler

tests/Unit/Module/{Feature}/
└── {Specific}{Feature}ContributorTest.php
```

### Priority Matrix

| Feature              | User Impact | Effort | Dependencies         |
|----------------------|-------------|--------|----------------------|
| Rename (fix)         | High        | Low    | Existing references  |
| Document Symbol      | High        | Low    | Existing code        |
| Definition           | Critical    | Medium | Declaration contrib  |
| Code Actions         | High        | High   | Type system, indexer |
| Formatting           | High        | Low    | External tool        |
| Document Highlight   | Medium      | Low    | References contrib   |
| Workspace Symbol     | Medium      | Low    | Index system         |
| Implementation       | High        | Medium | extends/impl index   |
| Folding Range        | Medium      | Low    | AST traversal        |
| Inlay Hints          | Medium      | Medium | Type resolver        |
| Semantic Tokens      | Low         | High   | Full AST analysis    |
| Call Hierarchy       | Medium      | High   | Usage indexers       |

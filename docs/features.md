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
| `textDocument/completion`         | Done        | 15 contributors, parallel execution, 1s timeout   |
| `textDocument/hover`              | Done        | 7 documentation contributors                      |
| `textDocument/declaration`        | Done        | 7 declaration contributors                        |
| `textDocument/definition`         | Done        | 4 definition contributors (class, function, method, variable) |
| `textDocument/references`         | Done        | 7 reference contributors                          |
| `textDocument/signatureHelp`      | Done        | 3 signature contributors                          |
| `textDocument/prepareRename`      | Done        | Returns range of symbol under cursor               |
| `textDocument/rename`             | Done        | Builds WorkspaceEdit from reference results        |
| `textDocument/diagnostic`         | Done        | PHP parse errors only                              |
| `textDocument/publishDiagnostics` | Done        | Push-model diagnostics                             |
| `textDocument/documentSymbol`     | Done        | Classes, interfaces, traits, enums, functions, members |
| `textDocument/documentHighlight`  | Done        | 2 contributors (variables, names)                  |
| `textDocument/formatting`         | Done        | Delegates to external formatter (php-cs-fixer/phpcbf) |
| `workspace/symbol`               | Done        | Project-wide symbol search via index               |

### Indexing System (16 indexers)

- **Declarations:** class, function, interface, trait, enum, class method, property, global constant, namespace
- **Usages:** class, function call, method call, property access, class constant
- **Relationships:** inheritance (extends/implements)

---

## Roadmap

### Phase 1 — Fix & Enable Existing Code ✓

All Phase 1 items have been completed.

- [x] **`textDocument/rename`** — implemented. Builds `WorkspaceEdit` from
  reference results using `TextEdit` entries per document.

- [x] **`textDocument/documentSymbol`** — enabled. Supports classes, interfaces,
  traits, enums, functions, methods, properties, constants, enum cases.

- [x] **Indexing on initialize** — re-enabled. `walkWorkspaceFolder` now
  indexes the project and loads PHP stubs on initialization.

### Phase 2 — Core Features (Must-Have)

Essential features that every competitive PHP LSP provides.

- [x] **`textDocument/definition`** (`definitionProvider`)
  Implemented with 4 contributors: class, function, method, variable.
  Separate from `declaration` — `definition` goes to the concrete
  implementation. Most editors bind `Ctrl+Click` / `F12` to `definition`.
  - *Architecture:* `DefinitionContributor` interface + `#[AsDefinitionContributor]`

- [x] **`textDocument/typeDefinition`** (`typeDefinitionProvider`)
  Implemented with 1 contributor using `TypeResolver` to resolve the type
  and then finding its declaration in the index.

- [x] **`textDocument/implementation`** (`implementationProvider`)
  Implemented with 1 contributor using the `InheritanceIndexer` to find
  classes that implement/extend the target interface or class.
  - *Architecture:* `ImplementationContributor` interface + `#[AsImplementationContributor]`

- [x] **`textDocument/codeAction`** (`codeActionProvider`)
  Implemented with 2 contributors:
  - **Import symbol** — add missing `use` statement for unresolved names
  - **Remove unused import** — quick-fix for unused `use` statements
  - *Architecture:* `CodeActionContributor` interface with `#[AsCodeActionContributor]`

- [x] **`textDocument/formatting`** (`documentFormattingProvider`)
  Implemented. Delegates to external tool (php-cs-fixer, phpcbf) via
  subprocess, returns full-document `TextEdit[]`.

- [x] **`textDocument/rangeFormatting`** (`documentRangeFormattingProvider`)
  Implemented. Formats a selected range by delegating to external formatter.

- [x] **`textDocument/documentHighlight`** (`documentHighlightProvider`)
  Implemented with 2 contributors: variable highlight (with read/write
  distinction) and name highlight (FullyQualified names).
  - *Architecture:* `DocumentHighlightContributor` + `#[AsDocumentHighlightContributor]`

- [x] **`workspace/symbol`** (`workspaceSymbolProvider`)
  Implemented. Searches classes, interfaces, traits, enums, functions,
  methods, properties, and constants via `IndexLookup`.

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

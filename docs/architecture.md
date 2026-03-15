# Application Architecture

## Design Philosophy

The Language Server architecture is built on the principle of **composition
from small, specialized parts**. Instead of monolithic handlers, each language
support feature is a set of small, isolated modules that the system assembles
into a whole at DI compilation and runtime.

This means:
- Adding a new feature does not require modifying existing code
- Each module can be tested and developed independently
- The system scales horizontally — more contributors = more features, without
  increasing core complexity

## System Layers

```
┌─────────────────────────────────────────────────────────────┐
│                      LSP Client (IDE)                       │
└──────────────────────────┬──────────────────────────────────┘
                           │ JSON-RPC / TCP
┌──────────────────────────▼──────────────────────────────────┐
│                    Transport Layer                           │
│              (php-lsp/kernel + ReactPHP)                     │
│         Accepts connections, parses JSON-RPC                │
└──────────────────────────┬──────────────────────────────────┘
                           │
┌──────────────────────────▼──────────────────────────────────┐
│                    Routing Layer                             │
│              (#[Route] attributes on controllers)           │
│    "textDocument/completion" → CompletionController          │
│    "textDocument/hover"      → HoverController               │
│    "initialize"              → InitializeController          │
└──────────────────────────┬──────────────────────────────────┘
                           │
┌──────────────────────────▼──────────────────────────────────┐
│                   Controller Layer                           │
│          Receives typed LSP parameters                       │
│          Creates Context, invokes Contributors               │
│          Collects results via Consumer                        │
└──────────┬───────────────┬───────────────┬──────────────────┘
           │               │               │
┌──────────▼───┐ ┌─────────▼────┐ ┌────────▼─────┐
│ Contributor  │ │ Contributor  │ │ Contributor  │   ...
│ (classes)    │ │ (functions)  │ │ (keywords)   │
└──────────┬───┘ └─────────┬────┘ └────────┬─────┘
           │               │               │
┌──────────▼───────────────▼───────────────▼──────────────────┐
│                   Infrastructure Layer                       │
│      Indexing, PsiFile (AST), Document Manager, TypeSystem   │
└─────────────────────────────────────────────────────────────┘
```

## Key Components

### 1. Kernel and DI Container

**`Application`** (`app/Application.php`) — the entry point, extends
`LanguageServerKernel` from `php-lsp/kernel`.

During boot, Application:
- Registers PHP attributes for contributor auto-configuration
- Adds `LSPCompilerPass` for custom container compilation
- Adds `DocumentManagerExtension` for document management

```php
foreach (self::ATTRIBUTES as $class => $tag) {
    $container->registerAttributeForAutoconfiguration(
        attributeClass: $class,
        configurator: static function (ChildDefinition $definition) use ($tag): void {
            $definition->addTag($tag);
        },
    );
}
```

This is the key mechanism: any class with the `#[AsCompletionContributor]`
attribute automatically receives the DI tag `lsp.completionContributors`, and
the controller gets it via `#[AutowireIterator('lsp.completionContributors')]`.

### 2. Controllers — LSP Method Entry Points

Controllers are located in `app/Controller/` and are bound to LSP methods
via routing attributes:

```php
#[AsController, Route('textDocument/completion')]
final class CompletionController { ... }
```

Each controller:
1. Receives typed LSP request parameters (e.g., `CompletionParams`)
2. Receives `EditorInterface` — the current state of open documents
3. Creates a **Context** — a wrapper with convenient access to AST, position, document
4. Invokes all registered **Contributors**
5. Collects results via a **Consumer** and builds the LSP response

**Current controllers:**

| Controller | LSP Method | Description |
|------------|-----------|-------------|
| `InitializeController` | `initialize` | Initialization, capability declaration, indexing trigger |
| `InitializedController` | `initialized` | Initialization confirmation |
| `SetTraceController` | `$/setTrace` | Trace level configuration |
| `CompletionController` | `textDocument/completion` | Code completion |
| `HoverController` | `textDocument/hover` | Hover documentation |
| `DeclarationController` | `textDocument/declaration` | Go to declaration |
| `DefinitionController` | `textDocument/definition` | Go to definition |
| `DocumentHighlightController` | `textDocument/documentHighlight` | Highlight symbol occurrences |
| `FormattingController` | `textDocument/formatting` | Document formatting |
| `ReferencesController` | `textDocument/references` | Find usages |
| `RenameController` | `textDocument/rename` | Symbol rename |
| `PrepareRenameController` | `textDocument/prepareRename` | Rename preparation |
| `SignatureHelpController` | `textDocument/signatureHelp` | Function signatures |
| `DiagnosticController` | `textDocument/diagnostic` | Pull diagnostics |
| `PublishDiagnosticsController` | `textDocument/publishDiagnostics` | Push diagnostics to client |
| `DocumentSymbolController` | `textDocument/documentSymbol` | Document symbols |

### 3. PsiFile — the AST Layer

**PSI (Program Structure Interface)** — an abstraction over the PHP file's AST,
built on `nikic/php-parser`.

```
PHP Source Code
    │
    ▼
PHPPsiFileParser  ──► parses via nikic/php-parser
    │
    ▼
SourceFileRoot    ──► AST root + metadata (errors, document)
    │
    ▼
PHPPsiFile        ──► wrapper with position-based lookup methods
    │
    ▼
InMemoryPsiFileManager ──► caching + invalidation by document version
```

**`PHPPsiFile`** provides methods for AST navigation:
- `findAtPosition(Position)` — all nodes containing the given position
- `findLastAtPosition(Position)` — the deepest (most specific) node at a position

**`InMemoryPsiFileManager`** — manages the AST cache:
- FIFO cache of 300 files with `setPermanent()` for pinning entries outside the eviction queue
- Automatic invalidation when document version changes
- On parse errors — sends diagnostics to the client via `textDocument/publishDiagnostics`

**`Tree`** — utility class for AST operations:
- Find child nodes by type (`childrenOfType`, `childrenOfTypes`)
- Navigate parent nodes (`parentOfType`, `getParentNodes`)
- Position/range calculations with cached line offsets and binary search (`toLineColumn`)
- Convert nodes to strings

### 4. Indexing System

Indexing is a background process that traverses all project files on open and
builds a structured index for fast lookups.

```
Project Files
    │
    ▼
Indexer (orchestrator)
    │
    ├── recursively walks project files
    ├── loads PHP stubs (built-in PHP functions/classes)
    │
    ▼
IndexerInterface[] (individual indexers)
    │
    │  Declaration indexers:
    ├── ClassIndexer              → key "php.classes.fqn"
    ├── InterfaceIndexer          → key "php.interfaces.fqn"
    ├── TraitIndexer              → key "php.traits.fqn"
    ├── FunctionIndexer           → key "php.functions.fqn"
    ├── ClassMethodIndexer        → key "php.methods.fqn"
    │
    │  Usage indexers:
    ├── ClassUsageIndexer         → key "php.classUsages"
    ├── MethodCallUsageIndexer    → key "php.methodCallUsages"
    ├── FunctionCallUsageIndexer  → key "php.functionCallUsages"
    ├── PropertyAccessUsageIndexer→ key "php.propertyAccessUsages"
    └── ClassConstantUsageIndexer → key "php.classConstantUsages"
         │
         ▼
    StorageInterface (InMemoryStorage)
         │  ├── read(indexKey) — iterate all entries
         │  └── readByField(indexKey, field, value) — O(1) via secondary indexes
         ▼
    IndexLookup ──► used by contributors for lookups
```

Each indexer:
- Implements `IndexerInterface` with `supports()`, `index()`, and `getKey()` methods
- Determines whether it can process a file (`supports`)
- Extracts data from the AST and returns key-value pairs (`index`)
- Data is stored in `InMemoryStorage` under the indexer's key

**Contributors use `IndexLookup`** to query the index:

```php
$this->indexLookup->findByKey(ClassIndexer::class)
```

### 5. Document Management

Document lifecycle management:

- **`DocumentLoaderInterface`** — loading documents from disk
- **`DocumentIdentifierFactoryInterface`** — creating document identifiers
- **`EditorInterface`** (from `php-lsp/ext-document-manager`) — current state
  of files open in the editor with versioning and incremental change support

### 6. Workspace and Project Management

- **`ProjectManager`** — holds the current project
- **`ProjectFactoryInterface`** — creates a project from a workspace folder URI
- On `initialize`, the server receives workspace folders and creates a project

### 7. Event Listeners

Server event handling via Symfony EventDispatcher:

- **`ServerListener`** — logs server start/stop events
- **`MessageListener`** — logs incoming/outgoing JSON-RPC messages

## How Small Parts Compose into the Whole

### Example: A Completion Request

The user types `str` in a PHP file and presses Ctrl+Space.

```
1. IDE sends JSON-RPC:
   {"method": "textDocument/completion", "params": {...}}

2. Kernel routes → CompletionController

3. Controller creates CompletionContext:
   - textDocument: file URI
   - position: line 5, column 3
   - editor: current document state
   - fileManager: AST access

4. Controller launches ALL contributors IN PARALLEL:

   ┌─ KeywordsCompletionContributor ──► "strict_types", "string", ...
   │
   ├─ ClassesCompletionContributor ──► "StringHelper", "StringBuilder", ...
   │    └─ uses IndexLookup → InMemoryStorage → ClassIndexer
   │
   ├─ FunctionCompletionContributor ──► "str_contains", "strlen", ...
   │    └─ uses IndexLookup → InMemoryStorage → FunctionIndexer
   │
   ├─ SuperglobalsCompletionContributor ──► (no matches)
   │
   └─ ShortcutCompletionContributor ──► (no matches)

   Each contributor:
   - Gets the AST node at cursor position via context.currentNode()
   - Extracts text from the node: "str"
   - Filters its data using PrefixMatcher
   - Writes results to its own Consumer

5. Timeout: 1 second per contributor

6. Controller merges results: array_merge(...$results)

7. Response to client: CompletionItem[] array
```

### Example: Adding a New Feature (Hover for Use Imports)

Suppose we need to show information about an imported class when hovering
over a `use` statement. Here is what we need to do:

```php
// app/Module/Documentation/UseStatementDocumentationContributor.php

#[AsDocumentationContributor]
final class UseStatementDocumentationContributor implements DocumentationContributor
{
    public function contribute(DocumentationContext $context, DocumentationConsumer $consumer): void
    {
        $node = $context->currentNode();

        if (!$node instanceof UseItem) {
            return;
        }

        $consumer(new MarkupContent(
            kind: MarkupKind::Markdown,
            value: "**Imported class:** `{$node->name}`",
        ));
    }
}
```

**One file created. Nothing else needed.** The system will automatically
discover the contributor and wire it into `HoverController`.

### Horizontal Scaling Principle

```
                    Controller
                       │
            ┌──────────┼──────────┐
            │          │          │
         [v1.0]     [v1.1]     [v2.0]
        Keyword     Classes    UseStmt     ◄── Each version simply adds
       Completion  Completion  Completion       new contributors without
                                                touching existing ones
```

The core (controllers, contexts, consumers) **remains stable and unchanged**.
All evolution happens in the contributor layer. This allows:

- Parallel development of different language support aspects
- Enabling/disabling features without recompiling the core
- Adding support for new language constructs with minimal changes

## Directory Structure and Its Logic

```
app/
├── Application.php               # Core — DI config, attribute registration
│
├── Controller/                   # ENTRY POINTS — bound to LSP methods
│   ├── InitializeController.php  #   Each controller = one LSP method
│   └── TextDocument/             #   Grouped by LSP namespace
│       ├── CompletionController.php
│       └── ...
│
├── Core/Contracts/               # CONTRACTS — interfaces and attributes
│   ├── Completion/               #   For each contributor type:
│   │   ├── CompletionContributor.php    # - interface
│   │   ├── AsCompletionContributor.php  # - registration attribute
│   │   ├── CompletionContext.php        # - request context
│   │   └── CompletionConsumer.php       # - result accumulator
│   ├── Declaration/
│   ├── Documentation/
│   ├── References/
│   ├── Signature/
│   ├── Indexing/
│   └── PrefixMatcher/
│
├── Module/                       # IMPLEMENTATIONS — concrete logic
│   ├── Completion/               #   Completion contributors (5)
│   │   ├── ClassesCompletionContributor.php
│   │   ├── FunctionCompletionContributor.php
│   │   ├── KeywordsCompletionContributor.php
│   │   ├── ShortcutCompletionContributor.php
│   │   └── SuperglobalsCompletionContributor.php
│   ├── Declaration/              #   Go-to-definition contributors (3)
│   ├── Documentation/            #   Hover documentation contributors (2)
│   ├── References/               #   Find usages contributors (7)
│   ├── Signature/                #   Function signature contributors (3)
│   ├── Indexing/                 #   Indexers (10) + storage
│   ├── PsiFile/                  #   AST parsing and navigation
│   ├── Document/                 #   Document loading
│   ├── Workspace/                #   Project management
│   ├── TypeSystem/               #   PHPStan-based type resolution
│   └── Notification/             #   Server notifications
│
├── Infrastructure/Symfony/       # INFRASTRUCTURE — DI compiler passes
│
└── Listener/                     # OBSERVERS — server event handling
```

Separation logic:
- **Controller** — thin, no business logic, orchestration only
- **Core/Contracts** — stable contracts, rarely change
- **Module** — actively evolving code, the main area of developer work
- **Infrastructure** — bridge between framework and application

## Asynchronous Execution and ReactPHP

The server runs on a single-threaded asynchronous model using the ReactPHP
event loop:

- TCP connections are served via non-blocking I/O
- Contributors in `CompletionController` execute via `React\Promise\all()`
  in parallel, with `timeout(..., 1.0)`
- Consumer uses `delay(0)` for cooperative multitasking — yields control to
  the event loop every 100 items or 10ms
- On error or timeout, a contributor returns partial results rather than
  breaking the entire request

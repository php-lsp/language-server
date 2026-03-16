# Architecture Analysis: PHP Language Server

> Objective architecture analysis with quantitative metrics, weakness
> identification, improvement recommendations, and comparison with leading
> LSP implementations.
>
> Date: 2026-03-15

---

## Table of Contents

1. [Current Architecture Overview](#1-current-architecture-overview)
2. [Quantitative Metrics](#2-quantitative-metrics)
3. [Architectural Patterns](#3-architectural-patterns)
4. [Weaknesses and Issues](#4-weaknesses-and-issues)
5. [Comparison with Other LSP/IDE Implementations](#5-comparison-with-other-lspide-implementations)
6. [Evaluation Matrix](#6-evaluation-matrix)
7. [Improvement Recommendations](#7-improvement-recommendations)
8. [Priorities](#8-priorities)

---

## 1. Current Architecture Overview

### Technology Stack

| Component | Technology |
|-----------|-----------|
| Language | PHP 8.4+ |
| DI Container | Symfony DependencyInjection |
| Async I/O | ReactPHP (event loop + promises) |
| AST Parser | nikic/php-parser v5 |
| Type System | PHPStan (type resolution) |
| Protocol | php-lsp/protocol (typed LSP DTOs) |
| Kernel | php-lsp/kernel (LanguageServerKernel) |

### Layers (top to bottom)

```
LSP Client (IDE)
    | JSON-RPC / TCP
Transport (php-lsp/kernel + ReactPHP)
    |
Routing (#[Route] attributes)
    |
Controllers (34 total)
    |
Context + Contributors (parallel) + Consumer
    |
Infrastructure: Indexing, PsiFile (AST), TypeSystem, DocumentManager
```

### Key Design Decisions

- **Contributor/Plugin pattern** — controllers delegate work to small
  contributor classes discovered via PHP attributes and DI tags.
- **Parallel execution** — completion contributors run via
  `React\Promise\all()` with a 1-second timeout.
- **In-memory indexing** — the entire index lives in `InMemoryStorage`
  (PHP arrays in process memory).
- **FIFO AST cache** — 300 files, evicting 10% of the oldest on overflow.

---

## 2. Quantitative Metrics

### Codebase Size

| Metric | Value |
|--------|-------|
| PHP files in `app/` | 245 |
| PHP files in `tests/` | 202 |
| Lines of code (`app/`) | 14,729 |
| Test-to-code ratio | 0.82 (by file count) |

### Module Size (LOC)

| Module | LOC | Files | Purpose |
|--------|-----|-------|---------|
| Indexing | 1,803 | 47 | Indexing and storage |
| Completion | 1,395 | 17 | Code completion |
| References | 674 | 7 | Find references |
| Declaration | 628 | 7 | Go-to-definition |
| PsiFile | 593 | 6 | AST parsing |
| Documentation | 534 | 7 | Hover documentation |
| Signature | 426 | 3 | Function signatures |
| TypeSystem | 310 | 5 | Type resolution |
| Controller | 1,004 | 34 | Request handlers |
| Core/Contracts | 433 | 46 | Interfaces and contracts |

### Coupling (Module Dependencies)

| Module | Ca (afferent) | Ce (efferent) | Instability (Ce/(Ca+Ce)) |
|--------|:---:|:---:|:---:|
| **PsiFile** | 96 | 1 | 0.01 |
| **Indexing** | 95 | 29 | 0.23 |
| Document | 6 | 1 | 0.14 |
| Notification | 3 | 0 | 0.00 |
| Workspace | 2 | 0 | 0.00 |
| **TypeSystem** | 1 | 3 | 0.75 |
| Completion | 0 | 42 | 1.00 |
| Declaration | 0 | 37 | 1.00 |
| Documentation | 0 | 26 | 1.00 |
| References | 0 | 26 | 1.00 |
| Signature | 0 | 15 | 1.00 |

**Interpretation:**
- **PsiFile and Indexing** are central modules that nearly everything
  depends on. Instability ~ 0 means they are stable, yet they contain
  concrete implementations (not abstractions), violating the Stable
  Abstractions Principle (SAP).
- Completion, Declaration, and others are fully unstable (Instability = 1),
  which is correct for leaf consumer modules.
- TypeSystem has Instability 0.75 — adequate for a module that is rarely
  depended upon but depends on external libraries.

### Abstractness

| Module | Total classes | Interfaces/Abstract | Abstractness |
|--------|:---:|:---:|:---:|
| Document | 4 | 2 | 0.50 |
| TypeSystem | 5 | 1 | 0.20 |
| Documentation | 7 | 1 | 0.14 |
| References | 7 | 1 | 0.14 |
| Indexing | 47 | 4 | 0.08 |
| Completion | 17 | 1 | 0.05 |
| PsiFile | 6 | 0 | **0.00** |
| Declaration | 7 | 0 | **0.00** |
| Signature | 3 | 0 | **0.00** |
| Workspace | 1 | 0 | **0.00** |

**Issue:** PsiFile (Ca=96, Abstractness=0.00) falls into the "Zone of Pain"
on Martin's diagram — highly stable but entirely concrete. Any change to
`InMemoryPsiFileManager` or `PHPPsiFile` will ripple through a massive
number of dependent modules.

---

## 3. Architectural Patterns

### Patterns in Use

| Pattern | Where Applied | Rating |
|---------|--------------|--------|
| **Strategy** | Contributor interfaces (CompletionContributor, etc.) | Good |
| **Observer** | Symfony EventDispatcher for server events | Good |
| **Template Method** | AbstractPhpIndexer (abstract `indexInternal`) | Good |
| **Consumer/Collector** | CompletionConsumer, ReferenceConsumer — result accumulation | Good |
| **FIFO Cache** | FifoCache for AST files | Adequate |
| **Service Locator** | `#[AutowireIterator]` for contributor lists | Adequate |
| **Repository** | StorageInterface / InMemoryStorage for the index | Basic |

### Missing Patterns (Critical for LSP)

| Pattern | Necessity | Status |
|---------|----------|--------|
| **Demand-driven computation / Incremental** | Critical | Partial (incremental re-indexing on file change) |
| **Cancel token / Cancellation** | High | Implemented (CancellationToken, CancellationTokenRegistry, $/cancelRequest) |
| **Virtual File System (VFS)** | High | Partial (Document layer) |
| **Persistent index / Serialization** | High | Missing |
| **Workspace change tracking** | High | Implemented (workspace/didChangeWatchedFiles) |

---

## 4. Weaknesses and Issues

### 4.1. Indexing — Full Recomputation Without Incrementality

**Problem:** `Indexer::index()` traverses all project files synchronously
on initialization. There is no mechanism for incremental updates when a
single file changes.

```php
// app/Module/Indexing/Indexer.php:36-48
public function index(Project $project): void
{
    foreach ($project as $file) {
        $this->walkFilesInternal($file, 0);  // Entire project
    }
    // + PHP stubs
    $this->walkFilesInternal($stubs, 0);
}
```

**Consequences:**
- Opening a project with 10,000+ files results in a long startup time.
- When a file changes, the index is not updated — data becomes stale.
- No ability for partial re-indexing.

**Severity:** CRITICAL

### 4.2. InMemoryStorage — Data Lost on Restart

**Problem:** The entire index is stored in PHP arrays. There is no
persistent storage. On server restart, the index is completely lost.

```php
// app/Module/Indexing/Storage/InMemoryStorage.php
class InMemoryStorage implements StorageInterface
{
    private array $entries = [];  // Everything in memory
}
```

**Consequences:**
- Every startup = full re-indexing.
- On large projects — unacceptable startup delay.
- Memory consumption grows linearly with project size.

**Severity:** HIGH

### 4.3. Linear Index Search — O(N)

**Problem:** Most contributors perform a full pass over all index entries
and filter by name/type:

```php
// Typical pattern in contributors:
foreach ($this->indexLookup->findByKey(ClassMethodIndexer::class) as $entry) {
    if ($entry->value->className !== $className) {
        continue;  // Linear filtering
    }
}
```

**Consequences:**
- Completion on a project with 50,000 methods = 50,000 iterations per
  contributor.
- Performance degrades as the project grows.

**Severity:** HIGH

### 4.4. Hardcoded Ignored Directory List

**Problem:** In `Indexer::walkFilesInternal()`, the list of ignored
directories is hardcoded:

```php
$ignored = [
    'node_modules', '.git', '.idea', 'config', 'resources',
    'runtime', 'psalm', 'rector', 'thecodingmachine',
    'aerospike', 'tests', 'mongodb', 'meta', 'rdkafka',
    'intl', 'swoole', 'wincache', 'couchbase', ...
];
```

**Consequences:**
- No user configurability.
- Some entries are domain-specific (aerospike, couchbase) and should not
  be in the base code.
- Duplication (`'tests'` listed twice).

**Severity:** MEDIUM

### 4.5. ~~Indexing Disabled at Initialization~~ (RESOLVED)

**Status:** Fixed. The early `return` has been removed and indexing runs
on initialization. `InitializeController::walkWorkspaceFolder()` now calls
`$this->indexer->index($project)` as expected.

### 4.6. ~~No Cancellation or Progress Notifications~~ (RESOLVED)

**Status:** Fixed. Cancellation is implemented via `CancellationToken` and
`CancellationTokenRegistry` (`app/Core/Cancellation/`). The `$/cancelRequest`
handler (`CancelRequestController`) cancels in-flight requests. Progress
notifications use `ProgressNotifier` with WorkDoneProgress protocol.

### 4.7. PsiFile — God Object Tendency

**Problem:** `InMemoryPsiFileManager` combines:
- File parsing
- AST caching
- Sending diagnostics to the client
- Loading documents from disk
- Cache invalidation by version

This violates the Single Responsibility Principle. 96 incoming dependencies
make refactoring risky.

**Severity:** MEDIUM

### 4.8. Commented-Out Debug Code

**Problem:** 7+ files contain commented-out debug code
(`dump()`, `echo`, `var_dump`):

```
app/Application.php:45      // dump(...)
app/Controller/TextDocument/DeclarationController.php:33   // dump(...)
app/Controller/TextDocument/ReferencesController.php:33    // dump(...)
app/Module/PsiFile/InMemoryPsiFileManager.php:74           // dump(...)
app/Module/Indexing/Indexer.php:80                         // echo(...)
```

**Severity:** LOW (code smell)

### 4.9. No Go-to-Definition for Arbitrary Types

**Problem:** `ClassMemberCompletionContributor` resolves the object type
only for `$this->`, `self::`, `static::`. There is no type resolution
for arbitrary variables (via TypeSystem).

**Consequences:**
- Completion after `$foo->` does not work if `$foo` is not `$this`.
- The core LSP function — intelligent completion — is limited.

**Severity:** HIGH

### 4.10. Asymmetric Controller Parallelism

**Problem:** `CompletionController` runs contributors in parallel via
`React\Promise\all()`, but `ReferencesController`, `HoverController`,
and `DeclarationController` execute them sequentially via `foreach`.

```php
// CompletionController — parallel
$results = await(all($promises));

// ReferencesController — sequential
foreach ($this->contributors as $contributor) {
    $contributor->contribute($context, $consumer);
}
```

**Consequences:**
- Inconsistent behavior.
- References/Hover are slower than they could be.

**Severity:** LOW (while the number of contributors is small)

---

## 5. Comparison with Other LSP/IDE Implementations

### 5.1. Intelephense (PHP LSP, TypeScript)

**Architecture:** Monolithic, TypeScript, closed-source.

| Aspect | Intelephense | php-lsp/language-server |
|--------|-------------|------------------------|
| Implementation language | TypeScript (Node.js) | PHP (ReactPHP) |
| Indexing | Persistent (SQLite/files), incremental | In-memory, full, non-incremental |
| Type resolution | Custom type inference engine | PHPStan (external dependency) |
| Cancellation | Yes (LSP cancellation protocol) | No |
| Performance | Optimized for 100k+ files | Not tested at scale |
| Extensibility | Closed (no plugins) | Open (contributor pattern) |
| Maturity | Stable (5+ years) | Pre-release |

**Conclusion:** Intelephense is significantly ahead in production-readiness
but lacks extensibility due to its closed-source nature.

### 5.2. Phpactor (PHP LSP, PHP)

**Architecture:** Extension-based, PHP, open-source.

| Aspect | Phpactor | php-lsp/language-server |
|--------|---------|------------------------|
| Language | PHP | PHP |
| Architecture | Extension system (extension container) | Contributor pattern (Symfony DI) |
| Indexing | File-based (JSON), incremental | In-memory, non-incremental |
| Type inference | Custom worse-reflection | PHPStan |
| Caching | Persistent (filesystem) | In-memory FIFO |
| Workspace events | `didChangeWatchedFiles` | Not handled |
| Maturity | Stable (7+ years) | Pre-release |

**Conclusion:** Phpactor is the closest project in spirit (PHP on PHP).
Its extension system is similar to the contributor pattern but more mature.
Key advantage — incremental indexing and file cache.

### 5.3. rust-analyzer (Rust LSP, Rust)

**Architecture:** Demand-driven (salsa framework), incremental computation.

| Aspect | rust-analyzer | php-lsp/language-server |
|--------|--------------|------------------------|
| Computation model | Demand-driven (lazy, memoized) | Eager (computed on request) |
| Incrementality | Full (salsa DB, fine-grained) | Absent |
| Cancellation | Yes (salsa revision tracking) | No |
| Memory | Managed (salsa GC, LRU) | Unmanaged (grows) |
| Concurrency | Multi-threaded (rayon) | Single-threaded (event loop) |
| Extensibility | Fixed (monolithic) | Plugin-based |

**Conclusion:** rust-analyzer is the gold standard of LSP architecture.
Its demand-driven model is not achievable in PHP, but principles of
incrementality and cancellation can be adapted.

### 5.4. TypeScript Language Server (tsserver)

| Aspect | tsserver | php-lsp/language-server |
|--------|---------|------------------------|
| Indexing | Program object, project references | Flat index |
| Incrementality | Incremental parser + checker | Absent |
| Modularity | Monolithic | Plugin-based |
| Diagnostics | Full type checker | Parse errors only |

### 5.5. clangd (C/C++ LSP)

| Aspect | clangd | php-lsp/language-server |
|--------|-------|------------------------|
| Index | Persistent YAML/binary, background indexer | In-memory |
| Parsing | Incremental (Clang AST), error-tolerant | Full reparse |
| Threading | Multi-threaded (thread pool) | Single-threaded |
| Cancellation | Full support | No |

---

## 6. Evaluation Matrix

Scores on a 10-point scale (10 = ideal).

### 6.1. Architectural Properties

| Property | Score | Comment |
|----------|:-----:|---------|
| **Modularity** | 8/10 | Excellent contributor separation. But PsiFile/Indexing are monolithic centers. |
| **Extensibility** | 9/10 | Adding a new contributor = 1 file. Better than most LSP implementations. |
| **Testability** | 7/10 | 121 test files. Good module coverage, but no integration/E2E tests. |
| **Scalability** | 3/10 | In-memory, linear search, no incrementality — does not scale. |
| **Performance** | 4/10 | Full index traversal, synchronous indexing, single thread. |
| **Fault tolerance** | 6/10 | Timeouts on contributors, exception catching. But no cancellation. |
| **Maintainability** | 7/10 | Clean code, good structure. But PsiFile is in the Zone of Pain. |
| **Maturity** | 3/10 | Pre-release, indexing disabled, debug code in production. |

### 6.2. Comparative Matrix with Other LSPs

| Property | php-lsp | Intelephense | Phpactor | rust-analyzer | clangd |
|----------|:-------:|:------------:|:--------:|:-------------:|:------:|
| Modularity | 8 | 5 | 7 | 6 | 5 |
| Extensibility | 9 | 3 | 8 | 4 | 3 |
| Incrementality | 1 | 8 | 6 | 10 | 9 |
| Persistent index | 1 | 9 | 7 | 10 | 10 |
| Type inference | 4 | 9 | 7 | 10 | 9 |
| Cancellation | 1 | 8 | 5 | 10 | 10 |
| Scalability | 3 | 8 | 6 | 10 | 9 |
| Documentation | 8 | 7 | 6 | 10 | 8 |
| **Average** | **4.4** | **7.1** | **6.5** | **8.8** | **7.9** |

### 6.3. Martin's Zone Diagram (Distance from Main Sequence)

```
Abstractness (A)
1.0 +--------------------------------+
    | Zone of               Document |
    | Uselessness         .          |
    |                    TypeSystem  |
0.5 |                  .             |
    |                                |
    |         Main Sequence ---------+
    |        /                       |
    |       /   Indexing .           |
    |      /                         |
0.0 | PsiFile .     Zone of Pain     |
    +--------------------------------+
   0.0    Instability (I)         1.0

PsiFile:    I=0.01, A=0.00 -> Distance=0.99 (deep in Zone of Pain)
Indexing:   I=0.23, A=0.08 -> Distance=0.69 (in Zone of Pain)
Document:   I=0.14, A=0.50 -> Distance=0.36 (close to Main Sequence)
TypeSystem: I=0.75, A=0.20 -> Distance=0.05 (on Main Sequence)
```

**PsiFile** is the most problematic module: maximum stability with zero
abstractness. Interfaces must be extracted.

---

## 7. Improvement Recommendations

### 7.1. [CRITICAL] Enable and Make Indexing Incremental

**Current state:** Indexing is completely disabled (`return` in
`walkWorkspaceFolder`).

**Recommendation:**

1. Remove the early `return`, restore the `indexer->index()` call.
2. Implement a `textDocument/didChange` event handler for partial
   re-indexing of the changed file.
3. Add `StorageInterface::delete(string $uri)` for removing stale
   entries before re-indexing.
4. Run indexing in the background via `React\EventLoop\Loop::addTimer()`.

```php
// Proposed approach:
public function reindexFile(VirtualFileInterface $file): void
{
    $this->storage->deleteByUri((string) $file->uri);
    $this->runIndexers($file);
}
```

### 7.2. [CRITICAL] Persistent Index

**Recommendation:** Add `FileSystemStorage` as an alternative to
`InMemoryStorage`:

- Serialize to JSON/MessagePack files in `.php-lsp/cache/`.
- On startup — load cache, validate by file mtime.
- Only changed files get re-indexed.
- `JsonSerializer` already exists and can serve as a foundation.

**Alternative:** SQLite via `ext-pdo_sqlite`:

```sql
CREATE TABLE symbols (
    key TEXT,
    name TEXT,
    fqn TEXT,
    uri TEXT,
    data BLOB,
    mtime INTEGER
);
CREATE INDEX idx_key_name ON symbols(key, name);
```

This solves both the O(N) search problem (via SQL indexes) and
persistence simultaneously.

### 7.3. [HIGH] Extract PsiFile Interfaces

**Problem:** PsiFile is in the Zone of Pain (I=0.01, A=0.00).

**Recommendation:**

```php
// app/Core/Contracts/PsiFile/PsiFileInterface.php
interface PsiFileInterface
{
    public function findAtPosition(Position $position): array;
    public function findLastAtPosition(Position $position): ?Node;
}

// app/Core/Contracts/PsiFile/PsiFileManagerInterface.php
interface PsiFileManagerInterface
{
    public function findPsiFile(EditorInterface $editor, TextDocumentIdentifier $id): ?PsiFileInterface;
}
```

All 96 dependencies should depend on interfaces, not on the concrete
`InMemoryPsiFileManager` and `PHPPsiFile`.

### 7.4. [IMPLEMENTED] Indexed Data Structures for Lookup

**Problem:** Linear O(N) search on every completion/reference request.

**Solution (implemented):** Added `readByField()` method to `InMemoryStorage`
with lazy secondary indexes:

```php
// O(1) lookup by field value (secondary index built on first call)
$storage->readByField('php.methods.fqn', 'className', $targetClass);
```

Secondary indexes are stored as `indexKey:field → fieldValue → list<Entry>`
hash maps, built lazily on first access and maintained incrementally on writes.

**Benchmark results:** 92% faster on 10K entries, 97% faster on 50K entries
compared to linear scan.

**Remaining:** Prefix search for completion (e.g., `findByPrefix`) and
adding `readByField` to `StorageInterface` interface are not yet implemented.

### 7.5. [HIGH] Type-Aware Completion via TypeSystem

**Problem:** Completion after `$foo->` does not work for arbitrary
variables.

**Recommendation:** Integrate `TypeResolver` into
`ClassMemberCompletionContributor`:

```php
// If not $this/self/static — resolve type via PHPStan
$typeResult = $this->typeResolver->resolveAtPosition($editor, $doc, $pos);
if ($typeResult !== null) {
    $className = $typeResult->type->describe(VerbosityLevel::typeOnly());
}
```

### 7.6. [MEDIUM] Cancellation Support

**Recommendation:**

1. Implement a `$/cancelRequest` handler.
2. Pass a `CancellationToken` into the context.
3. Contributors check the token in loops:

```php
foreach ($entries as $entry) {
    if ($context->isCancelled()) {
        return;
    }
    // ... processing
}
```

### 7.7. [MEDIUM] Split InMemoryPsiFileManager

**Recommendation:** Break it into:
- `PsiFileCache` — FIFO AST cache
- `PsiFileParser` — parsing (already exists as `PHPPsiFileParser`)
- `DiagnosticPublisher` — sending diagnostics to the client
- `PsiFileManager` — orchestration (thin facade)

### 7.8. [MEDIUM] Configurable Ignored Directories

**Recommendation:** Extract to a configuration file `.php-lsp.json`
or `initializationOptions`:

```json
{
  "exclude": ["vendor/tests", "node_modules", ".git"],
  "stubs": ["php-stubs"]
}
```

### 7.9. [LOW] Unify Controller Parallelism

**Recommendation:** Either all controllers run contributors in parallel
(via a shared trait/base), or all run them sequentially. Parallel is
preferred:

```php
// Shared trait for controllers
trait ParallelContributorRunner
{
    private function runContributors(array $contributors, $context, $consumer): void
    {
        $promises = array_map(
            fn($c) => timeout(async(fn() => $c->contribute($context, $consumer))(), 1.0),
            $contributors
        );
        await(all($promises));
    }
}
```

### 7.10. [LOW] Remove Commented-Out Debug Code

Remove all `// dump(...)`, `// echo(...)` from production code.
Use the logger with `debug` level instead.

---

## 8. Priorities

### Phase 1: Basic Functionality (Quick Wins)

1. Enable indexing (remove `return` in InitializeController)
2. Remove debug code
3. Extract ignored directory list to configuration

### Phase 2: Performance and Reliability

4. Incremental indexing (on file changes)
5. Secondary indexes in Storage (HashMap by className, name)
6. Extract PsiFile interfaces
7. Cancellation support

### Phase 3: Scalability

8. Persistent index (SQLite or file cache)
9. Type-aware completion via TypeSystem
10. Unify controller parallelism

### Phase 4: Production-Readiness

11. Background indexing with progress notifications
12. Workspace change tracking (`didChangeWatchedFiles`)
13. Integration/E2E tests
14. Memory profiling and limits

---

## Conclusion

The php-lsp/language-server architecture demonstrates an **excellent
architectural foundation** — the contributor pattern provides
extensibility that surpasses most competitors (9/10).

However, the **infrastructure layer** (indexing, storage, caching) is
at an early stage of development. The key blocker — disabled indexing
and lack of incrementality — renders the server non-functional in its
current state.

**Strengths:**
- Elegant contributor pattern with auto-discovery via attributes
- Clean separation into controllers, contracts, and modules
- Good test coverage (121 tests)
- High-quality documentation

**Key growth areas:**
- Incremental indexing (as in Phpactor/Intelephense)
- Persistent index (as in clangd/rust-analyzer)
- Type-aware intelligence (unlock PHPStan's potential)
- Cancellation (standard for production LSP)

By implementing Phases 1-3, the server can reach a competitive level
with Phpactor (score ~6.5) within several months of development.

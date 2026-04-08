# RoadRunner Go Indexing — Analysis & MVP

## Idea

Use RoadRunner (or standalone Go process) for PHP file indexing.
Go parses PHP files via tree-sitter, stores index in memory, and exposes
results to PHP via Goridge RPC (msgpack over TCP/Unix socket).

## Pros

### Performance
- **Parallel file walking**: Go goroutines can index thousands of files
  concurrently vs PHP's sequential `walkFilesInternal()`
- **Faster parsing**: tree-sitter (C-based, Go bindings) is significantly
  faster than nikic/php-parser for pure AST extraction
- **Lower memory per file**: Go structs are more compact than PHP objects;
  no GC pressure from millions of temporary AST nodes
- **Persistent index**: Go process survives PHP restarts — no re-indexing
  on every LSP server start
- **File watching**: Go's `fsnotify` provides native inotify/kqueue support
  for incremental re-indexing without polling

### Architecture
- **Offloads CPU work**: PHP event loop stays responsive for LSP requests
  while Go handles heavy indexing in background
- **RoadRunner ecosystem**: KV (in-memory/boltdb), Jobs (async task queue),
  custom plugins — battle-tested infrastructure
- **Language-agnostic index**: tree-sitter grammars exist for 100+ languages;
  future multi-language LSP support becomes trivial
- **Horizontal scaling**: multiple Go worker goroutines, configurable
  concurrency

### Developer Experience
- **Goridge protocol**: well-documented, fast binary protocol (msgpack codec)
- **RPC is simple**: PHP calls `$rpc->call('indexer.Search', $query)` — no
  HTTP overhead, no REST boilerplate
- **Decoupled deployment**: Go binary can be distributed separately, updated
  independently of PHP

## Cons

### Complexity
- **Two languages**: team must maintain both PHP and Go codebases
- **Build pipeline**: need Go toolchain + cross-compilation for
  linux/darwin/windows (CGO required for tree-sitter)
- **Debugging**: cross-process debugging is harder than single-process PHP
- **Deployment**: users must install Go binary alongside PHP PHAR

### Data Serialization
- **Serialization overhead**: every query crosses process boundary —
  msgpack encode/decode adds latency per call
- **Schema sync**: PHP data classes (ClassData, FunctionData, etc.) must
  stay in sync with Go structs manually
- **Large result sets**: returning thousands of entries over RPC is slower
  than in-process array access

### Operational
- **Process management**: must start/stop Go process alongside LSP server;
  crash recovery, health checks needed
- **Port conflicts**: TCP listener needs available port; Unix sockets need
  filesystem permissions
- **CGO dependency**: tree-sitter requires CGO → no pure-Go cross-compile,
  more complex CI matrix
- **RoadRunner version coupling**: custom plugins tied to specific RR version

### Feature Parity
- **tree-sitter vs php-parser**: tree-sitter produces CST (concrete syntax
  tree), not AST — no semantic info like `namespacedName`, resolved types,
  or docblock parsing out of the box
- **PHPStan integration**: type resolution currently relies on nikic/php-parser
  AST nodes; Go indexer would need separate type resolution or pass raw data
  back to PHP for enrichment
- **Partial re-index**: current system re-indexes single file on change;
  Go process needs same granularity via RPC

## MVP Approach

Instead of full RoadRunner plugin, MVP uses a **standalone Go binary** that:

1. Accepts a workspace path
2. Walks all `.php` files using goroutines
3. Parses each file with tree-sitter PHP grammar
4. Extracts class/function/method/property/constant declarations
5. Stores results in concurrent-safe in-memory map
6. Exposes Goridge-compatible RPC over TCP
7. PHP side implements `StorageInterface` calling Go RPC

### MVP Scope
- Class declarations only (name, FQN, position, modifiers, extends, implements)
- Benchmark: Go indexer vs PHP `ClassIndexer` on same file set
- No file watching, no incremental updates (full re-index only)

## Files

```
go-indexer/           # Go binary source
├── main.go           # Entry point, RPC server
├── indexer.go        # File walking + tree-sitter parsing
├── rpc.go            # RPC handlers for PHP
├── go.mod
└── go.sum

app/Module/Indexing/Storage/
└── GoIndexerClient.php    # PHP RPC client wrapper

tests/Unit/Module/Indexing/Storage/
└── GoIndexerClientTest.php
tests/Benchmark/
└── IndexingBenchmark.php
```

## Decision

Start with MVP benchmark to validate performance hypothesis before
committing to full RoadRunner integration. If Go indexing shows >3x
speedup on workspace of 1000+ files, proceed with full integration.

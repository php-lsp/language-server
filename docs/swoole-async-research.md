# Swoole Async Research: True Parallelism for LSP Contributors & Indexers

## Status

**Research / MVP** — proof-of-concept abstraction and benchmarks.

## Problem

The current async architecture has two bottlenecks:

1. **Contributors run pseudo-parallel.** Only `CompletionController` fans out
   via `Promise::all`. All other controllers (Declaration, Hover, References,
   Signature, Diagnostics) execute contributors **sequentially**.

2. **Indexing is sequential.** `Indexer::runIndexers()` wraps each indexer in
   `async()` but immediately `await()`s it — effectively blocking. Files are
   also processed one-by-one with no parallelism.

ReactPHP's event loop is single-threaded. CPU-bound tasks (AST parsing, type
resolution, hash lookups) never yield, so `Promise::all` does not provide real
parallelism for them.

## Swoole Overview

Swoole is a C extension providing coroutine-based concurrency for PHP:

| Feature           | ReactPHP                  | Swoole                              |
|-------------------|---------------------------|--------------------------------------|
| Concurrency model | Single-thread event loop  | Coroutines (green threads)           |
| I/O parallelism   | Cooperative (promises)    | Automatic yield on I/O               |
| CPU parallelism   | None (single thread)      | Multi-process workers                |
| Code style        | Promise chains / async    | Synchronous-looking                  |
| Extension         | Pure PHP (optional ext)   | Required C extension                 |
| PHP 8.4+          | Yes                       | Yes (Swoole 6.x)                     |

### Key APIs

```php
// Entry point — creates coroutine context
Co\run(function () {
    // Spawn coroutines
    go(function () { /* runs concurrently */ });

    // Parallel fan-out (direct Promise::all equivalent)
    $results = Swoole\Coroutine\batch([
        'a' => fn() => doWork(),
        'b' => fn() => doOtherWork(),
    ], timeout: 5.0);

    // WaitGroup for synchronization
    $wg = new Swoole\Coroutine\WaitGroup();
    $wg->add();
    go(function () use ($wg) { /*...*/ $wg->done(); });
    $wg->wait();
});

// Hook all PHP blocking functions to yield automatically
Swoole\Runtime::enableCoroutine();
```

### Runtime Hooks — Zero-Change Async

`Swoole\Runtime::enableCoroutine()` hooks PHP's native blocking functions at
the ZendVM level. All `php_stream`-based operations (fopen, file_get_contents,
PDO, curl, etc.) become coroutine-aware. Existing synchronous code in
contributors and indexers would work without modification.

## Current Architecture Analysis

### What uses async today

| Component              | Async? | Pattern                      | True parallel? |
|------------------------|--------|------------------------------|----------------|
| CompletionController   | Yes    | `Promise::all` + timeout     | I/O only       |
| DeclarationController  | No     | Sequential foreach           | No             |
| HoverController        | No     | Sequential foreach           | No             |
| ReferencesController   | No     | Sequential foreach           | No             |
| SignatureController    | No     | Sequential foreach           | No             |
| DiagnosticController   | No     | Sequential foreach           | No             |
| RenameController       | No     | Sequential foreach           | No             |
| Indexer::runIndexers   | Pseudo | `await(async(...))` per item | No             |
| CompletionConsumer     | Yes    | `delay(0)` yield             | Cooperative    |

### Where Swoole would help

1. **All controllers** — fan out contributors via `batch()` with true
   coroutine parallelism
2. **Indexer** — process multiple files concurrently, run indexers per file
   in parallel
3. **File loading** — `Runtime::enableCoroutine()` makes `file_get_contents`
   yield automatically, no need for ReactPHP filesystem adapter

## Proposed Architecture

### AsyncRunnerInterface

```
app/Core/Contracts/Async/AsyncRunnerInterface.php
```

Abstracts parallel execution. Two methods:
- `parallel(array $tasks, float $timeout): array` — fan out & collect
- `run(callable $task, float $timeout): mixed` — single task

### Implementations

| Class             | When to use                          |
|-------------------|--------------------------------------|
| `SyncAsyncRunner` | Tests, fallback                      |
| `ReactAsyncRunner`| Default (no ext-swoole required)     |
| `SwooleAsyncRunner`| When ext-swoole is available        |

### Migration path

```
Phase 1 (current MVP):
  ✅ AsyncRunnerInterface abstraction
  ✅ Three implementations (Sync, React, Swoole)
  ✅ Contract tests for all implementations
  ✅ Benchmark script

Phase 2 (integration):
  □ Inject AsyncRunnerInterface into controllers
  □ Replace CompletionController's manual Promise::all with runner
  □ Add async fan-out to Declaration, Hover, References, etc.
  □ Parallelize Indexer file processing

Phase 3 (Swoole server bridge):
  □ Create php-lsp/bridge-server-swoole package
  □ Swoole\Coroutine\Server for LSP TCP transport
  □ Runtime::enableCoroutine() at bootstrap
  □ Auto-detect ext-swoole and select runner

Phase 4 (optimization):
  □ Task workers for CPU-heavy indexing
  □ Connection pooling for concurrent requests
  □ Coroutine-aware caching
```

## Controller refactoring example

**Before (CompletionController):**
```php
$promises = [];
foreach ($this->contributors as $contributor) {
    $promises[] = $this->runContributor($contributor, $context);
}
$results = await(all($promises));
```

**After (any controller):**
```php
$tasks = [];
foreach ($this->contributors as $i => $contributor) {
    $tasks["contributor_{$i}"] = function () use ($contributor, $context) {
        $consumer = new CompletionConsumer();
        $contributor->contribute($context, $consumer);
        return $consumer->results;
    };
}
$results = $this->runner->parallel($tasks, timeout: 1.0);
return array_merge(...$results);
```

## Indexer parallelization example

**Before:**
```php
foreach ($this->indexers as $indexer) {
    if ($indexer->supports($file)) {
        await(async(function () use ($file, $indexer) {
            $this->storage->write($indexer::getKey(), $indexer->index($file), $file->uri);
        })());
    }
}
```

**After (parallel per file):**
```php
// Collect all files first, then index in batches
$tasks = [];
foreach ($files as $file) {
    $tasks[$file->uri] = function () use ($file) {
        foreach ($this->indexers as $indexer) {
            if ($indexer->supports($file)) {
                $this->storage->write($indexer::getKey(), $indexer->index($file), $file->uri);
            }
        }
    };
}
// Process in batches of N
foreach (array_chunk($tasks, 20, preserve_keys: true) as $batch) {
    $this->runner->parallel($batch, timeout: 10.0);
}
```

## Performance expectations

| Scenario                          | React (current)   | Swoole (expected)   |
|-----------------------------------|-------------------|---------------------|
| Completion (15 contributors)      | ~sequential CPU   | True parallel I/O   |
| Hover (7 contributors)            | Fully sequential  | Parallel fan-out    |
| Project indexing (1000 files)     | Sequential        | Batched parallel    |
| File reads during indexing        | Async (adapter)   | Auto-async (hooks)  |

**Note:** For pure CPU-bound work (AST parsing), single-process Swoole
coroutines will NOT be faster than ReactPHP — both are limited to one CPU
core. The gain comes from:
1. Parallel I/O during mixed workloads
2. Simpler code (no promise chains)
3. Multi-process workers for CPU-heavy indexing (Phase 4)

## Risks & Considerations

### ext-swoole installation burden
Swoole requires a C extension. This is a barrier for casual users.
**Mitigation:** Keep ReactPHP as default; Swoole is opt-in.

### Xdebug incompatibility
Swoole conflicts with Xdebug (except OpenSwoole 26.2+).
**Mitigation:** Developers use ReactPHP runner in debug mode.

### Windows support
Swoole has limited Windows support.
**Mitigation:** SyncAsyncRunner/ReactAsyncRunner as fallback.

### php-lsp/kernel coupling
The kernel uses ReactPHP internally. A Swoole server bridge would need to
either replace or wrap the kernel's event loop.
**Mitigation:** Phase 3 — investigate kernel extension points.

## Recommendation

**Use Swoole as an optional performance tier.** The `AsyncRunnerInterface`
abstraction allows us to:
- Keep ReactPHP as the zero-dependency default
- Auto-detect ext-swoole and upgrade to coroutine-based parallelism
- Get immediate wins by adding fan-out to all sequential controllers
- Plan for future multi-process indexing

The biggest immediate win is **Phase 2** — injecting `AsyncRunnerInterface`
into all controllers to parallelize contributor execution, regardless of
the backend (React or Swoole).

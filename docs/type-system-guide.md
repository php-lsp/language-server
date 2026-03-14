# Guide: Type System Implementation for PHP LSP

## Part 1: How Type Systems Work in Language Servers

### 1.1 Core Problem

A type system in an LSP answers one question: **"What is the type of this
expression at this position?"** Everything else — completion, hover, signatures,
references — is a consumer of this answer.

```php
$user = $repository->find(42);
$user->|
//      ^ What methods/properties can I suggest here?
//        Answer requires knowing the type of $user
```

### 1.2 Three Architectures for a Responsive IDE

The rust-analyzer team identified three fundamental architectures used by
mature IDEs. This classification is essential for choosing the right approach.

> Source: [Three Architectures for a Responsive IDE](https://rust-analyzer.github.io//blog/2020/07/20/three-architectures-for-responsive-ide.html)

#### Architecture A: Map-Reduce (IntelliJ, Sorbet)

1. **Indexing phase** — per-file, embarrassingly parallel. Builds a "dumb"
   index of declarations, symbols, and signatures. Does NOT resolve types
   or cross-file references.
2. **Analysis phase** — on-demand, uses the index to resolve only what is
   needed for the current query.

Key insight: **it is laziness, not incrementality, that makes an IDE fast.**
The index tells the system exactly which small set of files is relevant,
enabling it to skip huge swaths of code entirely.

#### Architecture B: Compilation Unit Snapshotting (C++, OCaml)

Snapshot compiler state after processing imports/headers. Restore from
snapshot when only the body of a compilation unit changes. Works for
languages with explicit compilation units.

#### Architecture C: Demand-Driven Incremental (rust-analyzer)

All computations are instrumented as queries in a dependency graph (Salsa
framework). Results are memoized. On input change, only queries whose
transitive dependencies actually changed are recomputed. If a recomputed
query produces the same result, invalidation stops (**early cutoff**).

**For PHP LSP, Architecture A is the best fit.** PHP has a natural per-file
compilation model. Build a dumb index during project open, then resolve
types lazily per-request. Architecture C is overkill for PHP's simpler
module system.

### 1.3 Specific Implementations

#### TypeScript (tsserver) — Lazy Control Flow Graph

TypeScript uses a two-phase design:

1. **Binder** — greedily builds a Control Flow Graph (CFG) during parsing.
   The CFG is a DAG of "flow nodes" attached to AST nodes. Each assignment,
   condition, or branch creates a flow node.

2. **Checker** — lazily evaluates types on demand. When you ask "what is
   the type of `x` at line 10?", it walks the CFG **backwards** from that
   point to the function entry, collecting type narrowings along the way.

Key insight: **types are not pre-computed** for every variable at every point.
They are resolved lazily only when queried. This saves enormous amounts of
work — most variables in a file are never queried during a single LSP request.

> Source: [Flow Nodes: How Type Inference Is Implemented](https://effectivetypescript.com/2024/03/24/flownodes/)

#### rust-analyzer — Salsa (Demand-Driven Incremental)

rust-analyzer uses the Salsa framework for incremental computation:

- All data is organized as **queries** (functions from key → value)
- Results are cached and invalidated only when inputs change
- Core invariant: **"typing inside a function body never invalidates global
  derived data"** — changing `foo()`'s body doesn't re-analyze `bar()`

Type inference is **lazy but not necessarily incremental** — per-function
inference runs from scratch when needed, but is only triggered when actually
queried (e.g., for completion inside that function).

> Source: [rust-analyzer Architecture](https://rust-analyzer.github.io/book/contributing/architecture.html)

#### Phpactor (worse-reflection) — Frame Walker

Phpactor uses a **Frame-based** approach:

- A **Frame** represents a scope (function body, closure, etc.)
- **FrameWalkers** traverse AST nodes and record variable types into frames
- When a variable is queried, the frame is walked to find the most recent
  type assignment
- Type narrowing: `instanceof` checks create a new variable entry in the
  frame with the narrowed type at the `if` block start, and "restore" the
  original type at the block end

> Source: [Phpactor's New Type System](https://www.dantleech.com/blog/2022/04/17/phpactors-new-type-system/)

### 1.3 Universal Architecture

All mature LSPs converge on a similar layered design:

```
Layer 1: Type Representation
    What data structures represent types?
    Union, intersection, generic, literal, etc.

Layer 2: Type Sources (Declaration-Level)
    Where do types come from?
    - Explicit type hints: function foo(int $x): string
    - PHPDoc annotations: @param, @return, @var, @template
    - Class reflection: property types, method signatures

Layer 3: Type Inference (Expression-Level)
    How to infer types for expressions?
    - $x = new Foo()          → type of $x is Foo
    - $x = $this->getUser()   → return type of getUser()
    - $x = 42                 → int (or literal 42)

Layer 4: Control Flow Analysis
    How do types change within a function body?
    - if ($x instanceof Foo)  → narrow $x to Foo inside the block
    - if ($x === null) return → after this line, $x is non-null
    - $x = something_else()  → reassignment changes type

Layer 5: Scope Management
    How to track variables across scopes?
    - Function/method scope
    - Closure scope (use variables)
    - Loop scope
    - Global scope
```

### 1.4 Type Comparison: TrinaryLogic

PHPStan introduced an important concept: type comparisons should not return
`bool` but **TrinaryLogic** (yes / no / maybe):

```
isSuperTypeOf(Type $other): TrinaryLogic
```

- `yes` — `int` is supertype of `int` (always true)
- `no` — `int` is supertype of `string` (never true)
- `maybe` — `mixed` is supertype of `int` (depends on context)

This is critical for PHP because of:
- `mixed` type (accepts everything, but you can't call methods on it)
- Union types with partial overlap
- Template type parameters before resolution
- Magic methods (`__call`) where the method *might* exist

Typhoon Type does not include this — it must be implemented as part of the
inference engine. This is a bounded task: a single `TypeComparator` class
with a visitor over Typhoon Type variants.

### 1.5 Two Fundamental Strategies

**Strategy A: Pre-compute Everything (PHPStan/Psalm approach)**

Walk the entire AST, maintain a `Scope` object that evolves as you process
each statement. At every node, the full type information is available.

- (+) Accurate — sees the full picture
- (+) Can detect errors
- (-) Slow — must process entire file
- (-) Expensive to re-run on every keystroke

**Strategy B: Lazy/On-Demand (TypeScript/rust-analyzer approach)**

Only resolve types when queried. Build lightweight structural metadata
(CFG, symbol table) eagerly, but defer actual type computation.

- (+) Fast — only computes what's needed
- (+) Scales to large codebases
- (-) More complex implementation
- (-) May miss some cross-function interactions

**For an LSP, Strategy B is superior.** An LSP needs to answer one question
at a time (the cursor position), not analyze the entire file. But for PHP
specifically, we can combine both: use lightweight pre-computation at the
declaration level, and lazy inference at the expression level.

### 1.6 PHP-Specific Challenges

**Dynamic typing and coercion.** PHP has implicit type coercion (int → float,
Stringable → string). This means `accepts()` must differ from `isSuperTypeOf()`:
`float` accepts `int` (coercion), but `int` is NOT a subtype of `float`.

**Magic methods.** `__get`, `__set`, `__call`, `__callStatic` make properties
and methods resolvable only at runtime. Solution: read `@property`, `@method`
PHPDoc annotations on the class, and provide extension points for frameworks.

**Docblocks as primary type source.** PHP's native type system is too weak
for rich tooling. PHPDoc (`@param`, `@return`, `@var`, `@template`) is the
primary source of generics, array shapes, conditional types. Documented types
should be **preferred** over declared types when both exist.

**Arrays as everything.** PHP arrays serve as lists, maps, tuples, and records.
Array shape syntax (`array{name: string, age: int}`) is essential. This is
a PHPDoc-only feature — no native equivalent.

**Generics via `@template`.** No native generics in PHP. Template resolution
must handle `@template T`, bounds (`T of SomeInterface`), variance
(`@template-covariant`), and propagation through inheritance (`@extends`,
`@implements`).

**Frameworks.** Laravel's `User::all()` resolves through `__callStatic` →
`Model` → `new static`. Without framework-aware stubs, naive analysis fails.
Strategy: provide a stub/extension system from the start.

---

## Part 2: Available PHP Libraries and Tools

### 2.1 Comparison Matrix

| Library | What It Does | Standalone? | Scope/Inference? | PHP | Speed |
|---------|-------------|-------------|-------------------|-----|-------|
| **PHPStan** | Full static analysis | No (internal API) | Yes (NodeScopeResolver) | 8.1+ | Slow (full analysis) |
| **Typhoon Type** | Type representation | Yes | No | 8.2+ | Fast |
| **Typhoon Reflection** | Static class reflection | Yes | No (class-level only) | 8.2+ | Fast (lazy) |
| **phpstan/phpdoc-parser** | PHPDoc → AST | Yes | No | 8.1+ | Fast |
| **type-lang/parser** | Type string → AST | Yes | No | 8.1+ | Fast |
| **nikic/php-parser** | PHP → AST | Yes | No | 7.4+ | Fast |
| **Phpactor worse-reflection** | Reflection + inference | Partially | Yes (Frame) | 8.1+ | Medium |

### 2.2 PHPStan — The Tempting Trap

PHPStan's `NodeScopeResolver` + `Scope::getType()` is the most complete PHP
type resolver available. The project already uses it in `PHPStanAnalyzer`.

**Why NOT to rely on it:**

1. **Not a library.** Ondřej Mirtes (creator) explicitly states the internal
   API is unstable and breaks between minor versions. There is no
   `phpstan/scope` package.

2. **Designed for batch analysis**, not interactive use. It walks the entire
   file, processes every statement, maintains full scope state. For an LSP
   that needs the type at ONE position, this is massive overkill.

3. **Heavy bootstrapping.** Creating a PHPStan container requires filesystem
   access, config parsing, reflection provider setup. The current
   `PHPStanAnalyzer` creates a new container factory on each call — this is
   extremely slow.

4. **Blocking.** PHPStan's analysis is synchronous and not designed for the
   ReactPHP event loop.

**Where PHPStan IS useful:**

- Diagnostics (run as external process, already in `DiagnosticController`)
- Extracting its `phpdoc-parser` as a standalone dependency
- Learning from its type hierarchy design

> Source: [PHPStan Issue #420: Reuse of the static analyzer](https://github.com/phpstan/phpstan/issues/420)

### 2.3 Typhoon — The Right Building Blocks

**[Typhoon Type](https://github.com/typhoon-php/type)** — standalone type
representation library. MIT license, PHP 8.2+, production-ready.

What it provides:
```php
use function Typhoon\Type\{
    intT, stringT, objectT, unionT, nullOrT,
    arrayShapeT, nonEmptyListT, closureT,
    intersectionT, literalT,
};

// Create types
$type = objectT(UserRepository::class);
$nullable = nullOrT(stringT);
$union = unionT(intT, stringT);
$shape = arrayShapeT(['name' => stringT, 'age' => intT]);
$generic = objectT(Collection::class, [objectT(User::class)]);

// Stringify
stringify($type); // "UserRepository"
```

**[Typhoon Reflection](https://github.com/typhoon-php/reflection)** —
static reflection that resolves PHPDoc types and templates. Does NOT run or
autoload reflected code.

```php
$reflector = TyphoonReflector::build();
$class = $reflector->reflectClass(UserRepository::class);

// Get method return type (including PHPDoc)
$method = $class->methods()['find'];
$returnType = $method->returnType(); // Type object

// Resolve generics
$resolver = $class->createTemplateResolver([objectT(User::class)]);
$concreteType = $returnType->accept($resolver);
```

**What Typhoon does NOT do:**
- No variable type inference inside function bodies
- No control flow analysis
- No scope tracking

This is exactly right — Typhoon provides **Layers 1-2** (type representation
and declaration-level sources), while **Layers 3-5** (inference, control flow,
scopes) must be implemented by us.

### 2.4 phpstan/phpdoc-parser — Standalone PHPDoc Parsing

A production-grade parser that converts PHPDoc comments into an AST.
Completely standalone, no dependency on PHPStan itself.

```php
use PHPStan\PhpDocParser\{Lexer\Lexer, Parser\*};

$lexer = new Lexer();
$parser = new PhpDocParser(new TypeParser(), new ConstExprParser());

$tokens = new TokenIterator($lexer->tokenize('/** @param array<string, int> $map */'));
$ast = $parser->parseTagValue($tokens, '@param');
// → ParamTagValueNode { type: GenericTypeNode, parameterName: '$map' }
```

Supports: unions, intersections, generics, array shapes, callables,
conditionals, `@template`, `@extends`, `@implements`.

### 2.5 type-lang/parser

Already in the project via `php-lsp/bridge-hydrator-type-lang`. A TypeLang
parser inspired by PHPStan/Psalm syntax. Can parse type strings into AST.
Potentially useful for the same purpose as `phpdoc-parser`.

### 2.6 Lessons from Phpactor (What NOT to Copy)

Phpactor's author admits the type system was "a mess" after years of
incremental development. Key problems:

- Tight coupling between reflection, type inference, and completion
- Frame-based analysis doesn't scale well to complex control flow
- Generics support was bolted on, requiring a full refactor
- Performance bottlenecks in the reflection/analysis package required making
  them non-blocking

**Lesson:** Design the type system as a separate, well-bounded layer from
the start. Don't let completion/declaration logic leak into type resolution.

---

## Part 3: Recommended Architecture

### 3.1 Design Principles

1. **Typhoon for type representation** — don't reinvent the wheel
2. **Own inference engine** — lightweight, lazy, designed for LSP
3. **Function-body isolation** — inference within a function body never
   requires re-analyzing other functions
4. **Lazy evaluation** — only resolve types when queried at a specific position
5. **PHPDoc as first-class source** — PHP's type system is too weak without it

### 3.2 Component Architecture

```
┌─────────────────────────────────────────────────────────┐
│                    Type System API                       │
│                                                         │
│   TypeResolver::resolveAtPosition(file, position): Type │
│   TypeResolver::resolveExpression(expr, scope): Type    │
│   TypeResolver::resolveNode(node): Type                 │
│                                                         │
└────────────────────────┬────────────────────────────────┘
                         │ uses
         ┌───────────────┼───────────────┐
         │               │               │
         ▼               ▼               ▼
┌─────────────┐ ┌────────────────┐ ┌──────────────────┐
│ Declaration │ │   Expression   │ │   Control Flow   │
│  Resolver   │ │   Inferrer     │ │   Analyzer       │
│             │ │                │ │                  │
│ - type hints│ │ - new Foo()    │ │ - if/else        │
│ - PHPDoc    │ │ - $a->method() │ │ - instanceof     │
│ - reflect.  │ │ - $a = expr   │ │ - null checks    │
│ - stubs     │ │ - static calls │ │ - assertions     │
└──────┬──────┘ └───────┬────────┘ └────────┬─────────┘
       │                │                    │
       ▼                ▼                    ▼
┌─────────────────────────────────────────────────────────┐
│                  Scope / Frame                           │
│                                                         │
│   Tracks variable types within a function body.         │
│   Built lazily — only processes statements up to the    │
│   queried position.                                     │
│                                                         │
└────────────────────────┬────────────────────────────────┘
                         │ uses
              ┌──────────┴──────────┐
              │                     │
              ▼                     ▼
┌──────────────────────┐  ┌──────────────────────┐
│   Typhoon Type       │  │  Typhoon Reflection  │
│   (representation)   │  │  (class metadata)    │
│                      │  │                      │
│   intT, objectT,     │  │  reflectClass()      │
│   unionT, etc.       │  │  methods(), props()  │
│                      │  │  templates           │
└──────────────────────┘  └──────────────────────┘
              │                     │
              ▼                     ▼
┌─────────────────────────────────────────────────────────┐
│              phpstan/phpdoc-parser                       │
│              (PHPDoc annotation parsing)                │
└─────────────────────────────────────────────────────────┘
```

### 3.3 Type Representation (Layer 1)

Use Typhoon Type as the canonical type representation. Add a thin adapter
layer for PHPStan phpdoc-parser AST → Typhoon Type conversion:

```php
namespace App\Module\TypeSystem;

use Typhoon\Type\Type;
use function Typhoon\Type\{objectT, intT, stringT, unionT, nullOrT, ...};

// The rest of the LSP only works with Typhoon\Type\Type.
// All type sources (hints, PHPDoc, inference) produce Typhoon types.
```

**Why Typhoon over custom types:**
- Rich type algebra out of the box (unions, intersections, generics, shapes)
- `stringify()` for hover display
- Template/generic resolution built in
- Compatible with PHPStan/Psalm type syntax
- Actively maintained, MIT license

### 3.4 Declaration Resolver (Layer 2)

Resolves types from declarations — things known without analyzing function
bodies. This data is **cacheable per file** and invalidated only when the
file changes.

```
Module/TypeSystem/
├── DeclarationResolver.php
│
│   Resolves types from:
│   ├── Native type hints (PHP 7.4+ property types, parameter types, return types)
│   ├── PHPDoc annotations (@param, @return, @var, @template)
│   ├── Class structure (extends, implements — via Typhoon Reflection)
│   └── PHP stubs (built-in function signatures)
│
├── PhpDocTypeMapper.php
│   Converts phpstan/phpdoc-parser AST nodes → Typhoon Type objects
│
└── StubRegistry.php
    Built-in PHP function/class type information
```

**The key design decision:** Declaration resolver uses **Typhoon Reflection**
for class metadata (methods, properties, inheritance, templates) and
**phpstan/phpdoc-parser** for PHPDoc parsing. This combination gives us
complete declaration-level type information without PHPStan's runtime.

### 3.5 Scope & Frame (Layer 3-4)

The scope tracks variable types within a function body. Built **lazily** —
only processes statements up to the queried position.

```
Module/TypeSystem/
├── Scope/
│   ├── Scope.php                 # Immutable snapshot of types at a point
│   ├── ScopeBuilder.php          # Walks statements, builds scope
│   ├── VariableTable.php         # Map of $varName → Type
│   └── ScopeContext.php          # Current class, function, namespace
```

**Scope is immutable.** Each statement that changes a variable type produces
a new Scope. This makes narrowing trivial:

```php
// Simplified model
class Scope {
    /** @var array<string, Type> */
    private array $variables;

    public function withVariable(string $name, Type $type): self {
        $clone = clone $this;
        $clone->variables[$name] = $type;
        return $clone;
    }

    public function getType(string $name): Type {
        return $this->variables[$name] ?? mixedT();
    }

    public function narrow(string $name, Type $narrowedType): self {
        // Intersection with current type
        return $this->withVariable($name,
            intersectionT($this->getType($name), $narrowedType)
        );
    }
}
```

### 3.6 Expression Inferrer (Layer 3)

Determines the type of an expression given a Scope:

```
Module/TypeSystem/
├── Inference/
│   ├── ExpressionInferrer.php    # Main dispatcher
│   ├── Strategy/
│   │   ├── NewExprStrategy.php           # new Foo() → Foo
│   │   ├── MethodCallStrategy.php        # $x->foo() → return type of foo()
│   │   ├── StaticCallStrategy.php        # Foo::bar() → return type
│   │   ├── PropertyFetchStrategy.php     # $x->prop → type of prop
│   │   ├── FunctionCallStrategy.php      # foo() → return type
│   │   ├── ArrayDimFetchStrategy.php     # $arr[0] → element type
│   │   ├── AssignStrategy.php            # $x = expr → type of expr
│   │   ├── BinaryOpStrategy.php          # $a + $b → numeric type
│   │   ├── CastStrategy.php             # (int)$x → int
│   │   ├── TernaryStrategy.php          # $a ? $b : $c → union
│   │   ├── ClosureStrategy.php          # function() → Closure type
│   │   ├── MatchStrategy.php            # match(...) → union of arms
│   │   └── LiteralStrategy.php          # 42 → int, 'str' → string
```

Each strategy is a pure function: `(Node\Expr, Scope) → Type`.

**Method call resolution** — the most critical path:

```
$user->getName()

1. Resolve type of $user from Scope → objectT(User::class)
2. Reflect User class via Typhoon Reflection
3. Find method "getName" → MethodReflection
4. Get return type → stringT
5. If class has @template, create TemplateResolver, resolve generics
```

### 3.7 Control Flow Analyzer (Layer 4)

Handles type narrowing through control structures. Walks the AST and
produces narrowed Scopes:

```
Module/TypeSystem/
├── Flow/
│   ├── FlowAnalyzer.php          # Walks if/else/match/try-catch
│   ├── TypeGuard.php             # Recognizes narrowing patterns
│   └── NarrowingRule/
│       ├── InstanceofRule.php    # $x instanceof Foo → narrow to Foo
│       ├── IsTypeRule.php        # is_string($x) → narrow to string
│       ├── NullCheckRule.php     # $x !== null → remove null
│       ├── ComparisonRule.php    # $x === 'value' → literal type
│       └── AssertRule.php        # assert($x instanceof Foo)
```

**How narrowing works step by step:**

```php
function process(string|int|null $x): void {
    // Scope: $x → string|int|null

    if ($x === null) {
        // Scope (true branch): $x → null
        return;
    }
    // Scope (after early return): $x → string|int

    if (is_string($x)) {
        // Scope (true branch): $x → string
        echo $x->|  // ← completion knows $x is string
    }
    // Scope (after): $x → int
}
```

The flow analyzer doesn't need to be complete on day one. Start with:
1. `instanceof` checks
2. `is_*()` function calls
3. Null comparisons (`=== null`, `!== null`)
4. Early returns (narrow the remaining scope)

### 3.8 Integration with the Indexing System

The type system needs data from the index. Two approaches:

**Option A — Typhoon Reflection replaces indexers for type data:**

Typhoon Reflection can reflect classes from source files without autoloading.
For type resolution, use Typhoon directly instead of the current indexers.
Keep the indexers for name lookups (FQN → file location) only.

**Option B — Enrich indexers, use both:**

Enrich the current indexers to store type information (parameter types, return
types, property types). The type system queries the index for fast lookups,
falls back to Typhoon Reflection for deep analysis.

**Recommendation: Option A for correctness, migrate to B for speed.**

Start with Typhoon Reflection — it's accurate and handles templates/generics.
Once working, profile and optimize hot paths by caching resolved types in the
index.

### 3.9 TypeComparator (TrinaryLogic)

A standalone class that implements type relationship checks:

```php
enum TrinaryLogic {
    case Yes;
    case No;
    case Maybe;

    public function and(self $other): self { ... }
    public function or(self $other): self { ... }
    public function negate(): self { ... }
}

class TypeComparator {
    // Is $a a supertype of $b?
    // intT->isSuperTypeOf(intT) = Yes
    // unionT(intT, stringT)->isSuperTypeOf(intT) = Yes
    // intT->isSuperTypeOf(stringT) = No
    // mixedT->isSuperTypeOf(intT) = Yes
    public function isSuperTypeOf(Type $a, Type $b): TrinaryLogic;

    // Does $a accept $b (with coercion)?
    // floatT->accepts(intT) = Yes (coercion)
    // intT->accepts(floatT) = No
    public function accepts(Type $a, Type $b, bool $strictTypes): TrinaryLogic;
}
```

This is implemented as a visitor over Typhoon Type variants. It is the
**critical correctness component** — all narrowing, completion filtering,
and diagnostics depend on it.

### 3.10 File Layout

```
app/Module/TypeSystem/
├── TypeResolver.php                  # Main API entry point
├── DeclarationResolver.php           # Layer 2: type hints + PHPDoc
├── PhpDocTypeMapper.php              # phpdoc-parser AST → Typhoon Type
├── Scope/
│   ├── Scope.php                     # Immutable variable type snapshot
│   ├── ScopeBuilder.php              # AST walker → builds scope to position
│   └── ScopeContext.php              # Class/function/namespace context
├── Inference/
│   ├── ExpressionInferrer.php        # Layer 3: expr → Type
│   └── Strategy/
│       ├── NewExprStrategy.php
│       ├── MethodCallStrategy.php
│       ├── PropertyFetchStrategy.php
│       ├── FunctionCallStrategy.php
│       └── LiteralStrategy.php
├── Flow/
│   ├── FlowAnalyzer.php              # Layer 4: control flow narrowing
│   └── NarrowingRule/
│       ├── InstanceofRule.php
│       ├── NullCheckRule.php
│       └── IsTypeRule.php
└── Reflection/
    ├── ReflectionProvider.php        # Wraps Typhoon + stubs + index
    └── StubRegistry.php              # Built-in PHP type info
```

---

## Part 4: Implementation Roadmap

### Phase 1 — Foundation (type representation + declaration types)

**Dependencies:** `typhoon/type`, `typhoon/reflection`, `phpstan/phpdoc-parser`

1. Add Typhoon packages to `composer.json`
2. Implement `PhpDocTypeMapper` — converts phpdoc-parser AST to Typhoon types
3. Implement `DeclarationResolver` — resolves types from hints and PHPDoc
4. Implement `ReflectionProvider` — wraps Typhoon Reflection with project
   file awareness
5. Write unit tests for type mapping

**Result:** Can answer "what is the declared type of parameter `$x` of
method `Foo::bar()`?"

### Phase 2 — Basic scope & expression inference

1. Implement `Scope` and `ScopeBuilder`
2. Implement `ExpressionInferrer` with strategies for:
   - Literals (int, string, float, bool, null, array)
   - `new Foo()`
   - Variable lookup from scope
   - Assignments
3. `ScopeBuilder` walks function body statements sequentially, stopping
   at the target position
4. Wire `TypeResolver` as the public API

**Result:** Can answer "what is the type of `$x` at line 15?" for simple
assignments.

### Phase 3 — Method/property/function resolution

1. Add `MethodCallStrategy` — resolve `$x->method()` via reflection
2. Add `StaticCallStrategy` — resolve `Foo::method()`
3. Add `PropertyFetchStrategy` — resolve `$x->property`
4. Add `FunctionCallStrategy` — resolve `foo()` return type
5. Template/generic resolution via Typhoon's `createTemplateResolver()`

**Result:** Can resolve type chains like `$repo->find(42)->getName()`.

### Phase 4 — Control flow narrowing

1. Implement `FlowAnalyzer` for `if`/`elseif`/`else`
2. Add `InstanceofRule`, `NullCheckRule`, `IsTypeRule`
3. Handle early returns (narrow remaining scope)
4. Handle `match` expression arms

**Result:** Type narrowing works for common patterns.

### Phase 5 — Integration with LSP features

1. Wire `TypeResolver` into `CompletionController` — for `$obj->|` completion
2. Wire into `HoverController` — show resolved types
3. Wire into `SignatureHelpController` — method signatures
4. Replace `PHPStanAnalyzer` with the new type system
5. Add `AsTypeContributor` pattern if needed for extensibility

**Result:** Intelligent, type-aware completion and hover.

### Phase 6 — Advanced (future)

- Closure/arrow function type propagation
- Array shape inference from assignments
- Generic type propagation through call chains
- Template type resolution for `@template` methods
- `@phpstan-assert` / `@psalm-assert` support
- `array_map`/`array_filter` return type inference
- Magic method (`__call`, `__get`) support

---

## Part 5: Lessons from Other PHP LSP Implementations

Cross-cutting findings from analyzing Phpactor, Intelephense, Psalm LSP,
PHPStan, Serenata, and felixfbecker/php-language-server:

### The "unknown type" problem is fatal

felixfbecker's LSP was essentially killed by not handling "type is unknown"
robustly. `Definition->type` could be `null`, but the system assumed it was
always set — causing cascading `TypeError` crashes. **Every expression must
always resolve to SOME type.** When inference fails, return `mixed`, never
`null`. This is non-negotiable.

### Declared types should skip body scanning

Intelephense explicitly does NOT scan function bodies when `@return`
annotations exist. This is a deliberate performance optimization — the
annotation is trusted. Our `ExpressionInferrer` should follow the same
strategy: if a method has a declared/documented return type, use it directly
without analyzing the body.

### Union-of-Atomics is the convergent representation

Psalm, PHPStan, and refactored Phpactor all converge on: all types are
unions of atomic types. Intersections are modeled as an atomic type inside
unions: `(A&B)|(C&D)` = `Union[Intersection[A,B], Intersection[C,D]]`.
Typhoon Type follows this pattern too.

### Frame/Scope is the universal pattern

Every implementation tracks variable types in a scope-like structure —
Phpactor's `Frame`, PHPStan's `MutatingScope`, Psalm's variable-type map.
The differentiator is control flow sophistication. Psalm's CNF-based
conditional tracking (multi-variable narrowing) is the most advanced but
also the most complex. Start simple and evolve.

### Dynamic PHP is the universal weakness

Every implementation handles `__call`/`__get` through PHPDoc annotations
(`@method`, `@property`), NOT by analyzing magic method bodies. PHPStan's
extension system is most flexible. PhpStorm meta files (supported by
Serenata, Intelephense) are a pragmatic alternative. Plan for an extension
point from the start.

### Indexing strategy: in-memory + lazy

In-memory only (felixfbecker) has painful cold starts. Database-backed
(Serenata/SQLite) persists but adds complexity. Best approach for us:
in-memory index built at project open (Architecture A: Map-Reduce), with
lazy type resolution per-request.

---

## Part 6: Key Decisions Summary

| Decision | Choice | Rationale |
|----------|--------|-----------|
| Type representation | Typhoon Type | Standalone, rich type algebra, compatible with PHPStan/Psalm syntax |
| Class reflection | Typhoon Reflection | Static, fast, supports PHPDoc + templates, no autoloading |
| PHPDoc parsing | phpstan/phpdoc-parser | Battle-tested, full PHPDoc support, standalone |
| Variable inference | Own implementation | No existing library does this as a reusable component |
| Control flow analysis | Own implementation | Must be lazy and LSP-optimized |
| PHPStan integration | Diagnostics only | Too heavy for interactive type resolution, unstable API |
| Architecture | Lazy, position-based | Inspired by TypeScript's approach — only resolve what's needed |

---

## Sources

- [Three Architectures for a Responsive IDE](https://rust-analyzer.github.io//blog/2020/07/20/three-architectures-for-responsive-ide.html)
- [Flow Nodes: How Type Inference Is Implemented (TypeScript)](https://effectivetypescript.com/2024/03/24/flownodes/)
- [rust-analyzer Architecture](https://rust-analyzer.github.io/book/contributing/architecture.html)
- [Salsa Algorithm Explained](https://medium.com/@eliah.lakhin/salsa-algorithm-explained-c5d6df1dd291)
- [Durable Incrementality (rust-analyzer blog)](https://rust-analyzer.github.io/blog/2023/07/24/durable-incrementality.html)
- [Phpactor's New Type System](https://www.dantleech.com/blog/2022/04/17/phpactors-new-type-system/)
- [PHPStan Scope docs](https://phpstan.org/developing-extensions/scope)
- [PHPStan Issue #420: Reuse of the static analyzer](https://github.com/phpstan/phpstan/issues/420)
- [Typhoon Type (GitHub)](https://github.com/typhoon-php/type)
- [Typhoon Reflection (GitHub)](https://github.com/typhoon-php/reflection)
- [Typhoon (GitHub)](https://github.com/typhoon-php/typhoon)
- [phpstan/phpdoc-parser (GitHub)](https://github.com/phpstan/phpdoc-parser)
- [type-lang/parser (Packagist)](https://packagist.org/packages/type-lang/parser)
- [TypeScript Narrowing (Official docs)](https://www.typescriptlang.org/docs/handbook/2/narrowing.html)
- [Psalm Type System (Plugin docs)](https://psalm.dev/docs/running_psalm/plugins/plugins_type_system/)
- [Psalm: The Truth Matters (flow-sensitive analysis)](https://psalm.dev/articles/the-truth-matters)
- [Intelephense Type System wiki](https://github.com/bmewburn/vscode-intelephense/wiki/Type-System)
- [Intelephense Documentation](https://intelephense.com/docs)
- [felixfbecker/php-language-server](https://github.com/felixfbecker/php-language-server)
- [PHPStan Type System docs](https://phpstan.org/developing-extensions/type-system)
- [PHPStan Class Reflection Extensions](https://phpstan.org/developing-extensions/class-reflection-extensions)
- [Serenata PHP Language Server](https://serenata.gitlab.io/)
- [Scaling gopls for the growing Go ecosystem](https://go.dev/blog/gopls-scalability)

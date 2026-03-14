# Plan: Indexers, Contributors & Providers Expansion

## Current State Analysis

### Existing Indexers (10)

**Declaration indexers (5):**

| Indexer | Key | Indexed Data |
|---------|-----|-------------|
| `ClassIndexer` | `php.classes.fqn` | FQN → FQN (string) |
| `InterfaceIndexer` | `php.interfaces.fqn` | FQN (list) |
| `TraitIndexer` | `php.traits.fqn` | FQN (list) |
| `FunctionIndexer` | `php.functions.fqn` | FQN → [name, startPos] |
| `ClassMethodIndexer` | `php.classMethods.fqn` | ClassName → list of method names |

**Usage indexers (5):**

| Indexer | Key | Indexed Data |
|---------|-----|-------------|
| `ClassUsageIndexer` | `php.usages.classes` | Class usage locations |
| `MethodCallUsageIndexer` | `php.usages.methodCalls` | Method call locations |
| `FunctionCallUsageIndexer` | `php.usages.functionCalls` | Function call locations |
| `PropertyAccessUsageIndexer` | `php.usages.propertyAccess` | Property access locations |
| `ClassConstantUsageIndexer` | `php.usages.classConstants` | Class constant usage locations |

### Existing Contributors (20)

**Completion (5):** Classes, Functions, Keywords, Superglobals, Shortcuts
**Declaration (3):** Class, ClassMethod, Function
**Documentation (2):** Docblock, NodesTrace
**Signature (3):** Function, Method, Constructor
**References (7):** Class, Function, Method, Property, Variable, Interface, ClassConstant
**Rename (0):** Controller exists, reuses reference contributors, incomplete

### Key Gaps

1. **Indexers store only names** — no position data, no type info, no relationships
2. **No enum support** anywhere (indexing, completion, declaration)
3. **No constant indexing** (class constants, global constants)
4. **No property indexing**
5. **No namespace indexing**
6. **No inheritance/implementation graph**
7. **References** — controller wired but zero contributors
8. **Rename** — stub, non-functional
9. **DocumentSymbol** — implemented but commented out (disabled)
10. **No interface/trait completion** contributors (only class completion exists)

---

## Phase 1: Enrich Indexers (Foundation)

Everything else depends on rich index data. Current indexers store bare FQN strings — need to store structured data with positions, types, and relationships.

### 1.1 — Enrich `Entry` Value Types

Create structured value objects instead of raw strings:

```
Storage/IndexData/
├── ClassData.php          # FQN, position, extends, implements[], isAbstract, isFinal
├── InterfaceData.php      # FQN, position, extends[]
├── TraitData.php          # FQN, position
├── EnumData.php           # FQN, position, backedType (string|int|null), implements[]
├── FunctionData.php       # FQN, position, params[], returnType
├── MethodData.php         # FQN, position, className, visibility, isStatic, isAbstract, params[], returnType
├── PropertyData.php       # name, className, visibility, type, isStatic, isReadonly
├── ConstantData.php       # name, ownerFQN (class or global), type, value
├── ParameterData.php      # name, type, hasDefault, isVariadic, isPromoted
└── NamespaceData.php      # FQN
```

### 1.2 — New Indexers

| Indexer | Key | Purpose |
|---------|-----|---------|
| `EnumIndexer` | `php.enums.fqn` | PHP 8.1+ enums |
| `PropertyIndexer` | `php.properties.fqn` | Class/trait properties (including promoted) |
| `ClassConstantIndexer` | `php.classConstants.fqn` | Class/interface/enum constants |
| `GlobalConstantIndexer` | `php.constants.fqn` | `const` and `define()` |
| `NamespaceIndexer` | `php.namespaces.fqn` | Namespace declarations |
| `InheritanceIndexer` | `php.inheritance` | Parent-child and implementation relationships |

### 1.3 — Enrich Existing Indexers

- **ClassIndexer** → store `ClassData` (extends, implements, abstract/final, position range)
- **InterfaceIndexer** → store `InterfaceData` (extends, position range)
- **TraitIndexer** → store `TraitData` (position range)
- **FunctionIndexer** → store `FunctionData` (params, returnType, position range)
- **ClassMethodIndexer** → store `MethodData` (visibility, static, abstract, params, returnType)

---

## Phase 2: Completion Contributors

### 2.1 — Missing Type Completion

| Contributor | Index Source | CompletionItemKind |
|-------------|------------|-------------------|
| `InterfaceCompletionContributor` | `InterfaceIndexer` | `InterfaceKind` |
| `TraitCompletionContributor` | `TraitIndexer` | `ClassKind` |
| `EnumCompletionContributor` | `EnumIndexer` | `EnumKind` |
| `ConstantCompletionContributor` | `GlobalConstantIndexer` | `ConstantKind` |

### 2.2 — Context-Aware Completion

| Contributor | Context | What It Completes |
|-------------|---------|-------------------|
| `ClassMemberCompletionContributor` | `$obj->▏` or `self::▏` | Methods + properties of resolved type |
| `ClassConstantCompletionContributor` | `ClassName::▏` | Class constants |
| `EnumCaseCompletionContributor` | `EnumName::▏` | Enum cases |
| `UseStatementCompletionContributor` | `use ▏` | FQNs from all type indexers |
| `NamespaceCompletionContributor` | `namespace ▏` | Known namespaces |
| `VariableCompletionContributor` | `$▏` | Variables in current scope |
| `PropertyCompletionContributor` | Inside class body | `$this->` property suggestions |

### 2.3 — Snippet Expansion

Expand `ShortcutCompletionContributor`:
- `foreach`, `for`, `while`, `do`, `if/else`, `try/catch`, `match`
- `/** */` docblock template
- Promoted constructor parameter (`__construct(private ...)`)

---

## Phase 3: Declaration Contributors (Go-To-Definition)

| Contributor | Handles Navigation To | Depends On |
|-------------|----------------------|------------|
| `InterfaceDeclarationContributor` | Interface definitions | `InterfaceIndexer` |
| `TraitDeclarationContributor` | Trait definitions | `TraitIndexer` |
| `EnumDeclarationContributor` | Enum definitions | `EnumIndexer` |
| `PropertyDeclarationContributor` | `$this->prop` → property definition | `PropertyIndexer` |
| `ClassConstantDeclarationContributor` | `Foo::BAR` → constant definition | `ClassConstantIndexer` |
| `GlobalConstantDeclarationContributor` | `CONST_NAME` → const definition | `GlobalConstantIndexer` |
| `VariableDeclarationContributor` | `$var` → first assignment/param | AST analysis (no index) |

Also fix existing: `ClassDeclarationContributor` currently returns `Range(0,0)` — should use actual position from enriched index.

---

## Phase 4: Reference Contributors (Find All Usages) — DONE

All 7 reference contributors have been implemented:

| Contributor | Finds Usages Of | Status |
|-------------|----------------|--------|
| `ClassReferenceContributor` | Classes | Implemented |
| `FunctionReferenceContributor` | Functions | Implemented |
| `MethodReferenceContributor` | Methods | Implemented |
| `PropertyReferenceContributor` | Properties | Implemented |
| `ClassConstantReferenceContributor` | Class constants | Implemented |
| `InterfaceReferenceContributor` | Interfaces | Implemented |
| `VariableReferenceContributor` | Variables | Implemented |

Usage indexers were also added to support reference lookups (ClassUsageIndexer,
MethodCallUsageIndexer, FunctionCallUsageIndexer, PropertyAccessUsageIndexer,
ClassConstantUsageIndexer).

---

## Phase 5: Documentation Contributors (Hover)

| Contributor | Hover On | Shows |
|-------------|---------|-------|
| `ClassDocumentationContributor` | Class name | Docblock + signature + file location |
| `FunctionDocumentationContributor` | Function call | Docblock + signature + params |
| `MethodDocumentationContributor` | Method call | Docblock + class::method signature |
| `PropertyDocumentationContributor` | `$this->prop` | Type + docblock + visibility |
| `ConstantDocumentationContributor` | `Foo::BAR` | Value + type + docblock |
| `VariableDocumentationContributor` | `$var` | Inferred type from assignment/param |
| `UseStatementDocumentationContributor` | `use Foo\Bar` | Full class info from index |

---

## Phase 6: Signature Contributors — DONE

All 3 signature contributors have been implemented:

| Contributor | Trigger | Status |
|-------------|---------|--------|
| `FunctionSignatureContributor` | `func(▏)` | Implemented |
| `MethodSignatureContributor` | `$obj->method(▏)` | Implemented |
| `ConstructorSignatureContributor` | `new Foo(▏)` | Implemented |

---

## Phase 7: Activate & Complete Stubs

### 7.1 — DocumentSymbol

`DocumentSymbolController` is fully implemented but disabled (route attribute commented out). Needs:
- Uncomment `#[AsController, Route('textDocument/documentSymbol')]`
- Enable `documentSymbolProvider` in `InitializeController` capabilities
- Add support for interfaces, traits, enums, constants (currently only classes + functions)

### 7.2 — Rename

`RenameController` is a stub reusing `ReferenceContributor`. Once references work:
- Implement `TextEdit` generation from reference locations
- Implement `PrepareRenameController` properly (validate rename target, return range)
- Consider adding a dedicated `RenameContributor` interface for complex renames

### 7.3 — Workspace Symbols

New controller `workspace/symbol` — search symbols across the entire project:
- Reuses all indexers for lookup
- Returns `SymbolInformation[]` with location, kind, container

---

## Phase 8: Type Resolution System — PARTIALLY DONE

The foundation has been implemented in `Module/TypeSystem/` using PHPStan
as the type inference engine:

### Implemented

```
Module/TypeSystem/
├── TypeResolverInterface.php     # Contract: resolveAtPosition, resolveVariableAtPosition
├── TypeResolver.php              # Implementation using PHPStan's NodeScopeResolver
├── TypeResult.php                # Value object wrapping PHPStan Type + Scope + Node
├── PHPStanBootstrap.php          # Boots PHPStan DI container, provides ScopeFactory/NodeScopeResolver
└── UriHelper.php                 # Converts file:// URIs to filesystem paths
```

- `DocblockDocumentationContributor` uses `TypeResolverInterface` for hover type display
- PHPStan is booted lazily on first type resolution request
- Type results include both short (`typeOnly`) and detailed (`precise`) descriptions

### Remaining Work

Many features are still limited without deeper integration:
- `$obj->▏` completion (need to know type of `$obj`)
- Method signature on `$obj->method(▏)`
- Variable type tracking across control flow

---

## Priority & Dependency Graph

```
Phase 1 (Indexers)
  ├──► Phase 2 (Completion)      — needs enriched index
  ├──► Phase 3 (Declaration)     — needs position data
  ├──► Phase 4 (References)      — needs full AST + index
  │      └──► Phase 7.2 (Rename) — needs references
  ├──► Phase 5 (Documentation)   — needs docblock data
  └──► Phase 6 (Signatures)      — needs param data

Phase 7.1 (DocumentSymbol)       — independent, can be done now
Phase 7.3 (Workspace Symbols)    — needs enriched index

Phase 8 (Type Resolution)        — independent foundation,
  └──► unlocks advanced completion, signatures, hover
```

### Recommended Implementation Order

1. **Phase 1** — Enrich indexers (everything depends on this)
2. **Phase 7.1** — Activate DocumentSymbol (quick win, already coded)
3. **Phase 2.1** — Interface/Trait/Enum completion (simple, high-value)
4. **Phase 3** — Fix declarations to use actual positions
5. **Phase 5** — Hover documentation (high user impact)
6. **Phase 4** — References (complex but critical)
7. **Phase 6** — Method signatures
8. **Phase 2.2** — Context-aware completion (needs type resolution)
9. **Phase 7.2** — Rename (after references work)
10. **Phase 8** — Type resolution (unlocks advanced features)
11. **Phase 7.3** — Workspace symbols

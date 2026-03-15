# Plan: Indexers, Contributors & Providers Expansion

## Current State Analysis

### Existing Indexers (16)

**Declaration indexers (11):**

| Indexer | Key | Indexed Data |
|---------|-----|-------------|
| `ClassIndexer` | `php.classes.fqn` | FQN → ClassData (extends, implements, abstract/final, positions) |
| `InterfaceIndexer` | `php.interfaces.fqn` | FQN → InterfaceData (extends, positions) |
| `TraitIndexer` | `php.traits.fqn` | FQN → TraitData (positions) |
| `FunctionIndexer` | `php.functions.fqn` | FQN → FunctionData (params, returnType, positions) |
| `ClassMethodIndexer` | `php.classMethods.fqn` | ClassName::method → MethodData (visibility, static, params, returnType, positions) |
| `EnumIndexer` | `php.enums.fqn` | FQN → EnumData (backedType, implements, positions) |
| `PropertyIndexer` | `php.properties.fqn` | ClassName::$prop → PropertyData (visibility, type, static, readonly, positions) |
| `ClassConstantIndexer` | `php.classConstants.fqn` | ClassName::CONST → ConstantData (type, value, positions) |
| `GlobalConstantIndexer` | `php.constants.fqn` | Name → ConstantData (value, positions) |
| `NamespaceIndexer` | `php.namespaces.fqn` | FQN → NamespaceData (positions) |
| `InheritanceIndexer` | `php.inheritance` | [child, parent, relation] tuples |

**Usage indexers (5):**

| Indexer | Key | Indexed Data |
|---------|-----|-------------|
| `ClassUsageIndexer` | `php.classUsages` | Class usage locations |
| `MethodCallUsageIndexer` | `php.methodCallUsages` | Method call locations |
| `FunctionCallUsageIndexer` | `php.functionCallUsages` | Function call locations |
| `PropertyAccessUsageIndexer` | `php.propertyAccessUsages` | Property access locations |
| `ClassConstantUsageIndexer` | `php.classConstantUsages` | Class constant usage locations |

### Existing Contributors (39)

**Completion (15):** Classes, Functions, Keywords, Superglobals, Shortcuts (with control flow snippets), Interfaces, Traits, Enums, Constants, ClassMembers, ClassConstants, EnumCases, UseStatements, Namespaces, Variables
**Declaration (7):** Class (+ Interface + Trait + Enum), ClassMethod, Function, Property, ClassConstant, GlobalConstant, Variable
**Documentation (7):** Docblock, NodesTrace, Class, Function, Method, Property, Constant
**Signature (3):** Function, Method, Constructor
**References (7):** Class, Function, Method, Property, Variable, Interface, ClassConstant
**Rename:** Functional — uses ReferenceContributors to find all usages, generates TextEdits

### Key Gaps (Resolved)

1. ~~Indexers store only names~~ — **DONE**: All indexers now store structured IndexData objects with positions, types, and relationships
2. ~~No enum support~~ — **DONE**: EnumIndexer, EnumCompletionContributor, EnumCaseCompletionContributor, enum in DocumentSymbol
3. ~~No constant indexing~~ — **DONE**: ClassConstantIndexer, GlobalConstantIndexer
4. ~~No property indexing~~ — **DONE**: PropertyIndexer
5. ~~No namespace indexing~~ — **DONE**: NamespaceIndexer
6. ~~No inheritance/implementation graph~~ — **DONE**: InheritanceIndexer
7. ~~References — controller wired but zero contributors~~ — **Already implemented** (7 reference contributors)
8. ~~Rename — stub, non-functional~~ — **DONE**: Functional rename using reference contributors
9. ~~DocumentSymbol — disabled~~ — **DONE**: Activated with support for classes, interfaces, traits, enums, constants
10. ~~No interface/trait completion~~ — **DONE**: InterfaceCompletionContributor, TraitCompletionContributor

---

## Phase 1: Enrich Indexers (Foundation) — DONE

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

## Phase 2: Completion Contributors — DONE

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

## Phase 3: Declaration Contributors (Go-To-Definition) — DONE

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

## Phase 5: Documentation Contributors (Hover) — DONE

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

## Phase 7: Activate & Complete Stubs — DONE

### 7.1 — DocumentSymbol — DONE

`DocumentSymbolController` activated with full support:
- Route attribute uncommented and controller enabled
- `documentSymbolProvider` enabled in `InitializeController` capabilities
- Supports classes, interfaces, traits, enums (with cases), constants, functions, methods, properties

### 7.2 — Rename — DONE

`RenameController` implemented using `ReferenceContributor`:
- Uses RenameParams with newName to collect all references and generate TextEdits
- Returns WorkspaceEdit with changes grouped by file URI
- `PrepareRenameController` validates renameable symbols (Variable, Identifier, Name nodes)

### 7.3 — Workspace Symbols — DONE

`WorkspaceSymbolController` at `workspace/symbol` — searches symbols across the project:
- Queries all type indexers (classes, interfaces, traits, enums, functions, methods, properties, constants)
- Uses StrContainsMatcher for fuzzy query filtering
- Returns `SymbolInformation[]` with location, kind, and container name
- `workspaceSymbolProvider` registered in InitializeController capabilities

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

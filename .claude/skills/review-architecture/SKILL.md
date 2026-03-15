---
name: review-architecture
description: Review architecture for dependency violations, layer boundary crossings, and coupling issues. Run last in the review pipeline — after tests, static analysis, and /review-docs.
disable-model-invocation: false
allowed-tools: Read, Glob, Grep
---

# Review: Architecture

## Pipeline Position

Run **last** — after tests, static analysis, and `/review-docs` have passed.
This is the final quality gate before committing.

## Goal

Detect dependency crossings between modules, broken layer boundaries, and
coupling issues. Prevent small violations from compounding.

## Architecture Rules

### Layer Hierarchy

```
Controller (top) → Core/Contracts → Module (bottom)
                                  → Infrastructure
```

- **Controllers** depend on: `Core/Contracts`, `Module` services (via DI).
- **Core/Contracts** must NOT depend on: `Module`, `Controller`, `Infrastructure`.
- **Module** depends on: `Core/Contracts`, other `Module` (with restrictions).
- **Infrastructure** depends on: `Core/Contracts`.
- **Listener** depends on: `Module` services for logging/notifications.

### Allowed Cross-Module Dependencies

Modules live in `app/Module/`. Each is an independent context.

| Consumer | May depend on |
|----------|---------------|
| Completion | Indexing, PsiFile, Document, TypeSystem |
| Declaration | Indexing, PsiFile, Document, TypeSystem |
| Definition | Indexing, PsiFile, Document, TypeSystem |
| Documentation | Indexing, PsiFile, Document, TypeSystem |
| Highlight | PsiFile, Document |
| References | Indexing, PsiFile, Document, TypeSystem |
| Signature | Indexing, PsiFile, Document, TypeSystem |
| Indexing | PsiFile, Document, Workspace |
| TypeSystem | PsiFile, Document |
| Notification | (none) |
| Workspace | (none) |
| Document | (none) |
| PsiFile | Document |

### Forbidden Patterns

- Infrastructure modules (PsiFile, Indexing, Document, Workspace) must NOT
  depend on feature modules (Completion, Declaration, References, etc.).
- Feature modules must NOT depend on each other (e.g., Completion must NOT
  import from References).
- No circular dependencies.
- Controllers must NOT contain business logic — only orchestration.

### Dependency Direction

```
Feature modules (unstable, I=1.0)
    ↓
Infrastructure modules (stable, I≈0)
    ↓
Core/Contracts (abstract, stable)
```

Stable modules should be abstract (interfaces). Concrete implementations in
stable modules = Zone of Pain.

## Review Steps

### 1. Scan Imports

For every changed PHP file, check `use App\Module\{OtherModule}\...` statements.
Verify against the allowed dependencies table. Flag:

- Feature-to-feature imports (e.g., Completion → References)
- Infrastructure-to-feature imports (e.g., Indexing → Completion)
- Contracts depending on implementations
- Circular dependency chains

### 2. Controller Thickness

Controllers in `app/Controller/` must be thin:
- Create context, invoke contributors, collect results.

Flag controllers that:
- Contain business logic (filtering, transforming, computing)
- Directly access storage/index without contributors
- Exceed ~50 lines of logic

### 3. Contract Stability

Files in `app/Core/Contracts/`:
- Only interfaces, attributes, context DTOs, consumers
- No concrete implementations
- No imports from `app/Module/`

### 4. Module Cohesion

- All classes in a module relate to its purpose
- Utility classes serving multiple modules belong in `Core/`
- Flag misplaced classes

### 5. New Contributor Registration

If a new contributor was added:
- Correct attribute (`#[AsCompletionContributor]`, etc.)
- Correct interface implemented
- Placed in correct `app/Module/` subdirectory
- No logic duplication with existing contributors

### 6. Coupling Metrics

For touched modules, evaluate against `docs/architecture-analysis.md`:
- **Instability** = Ce / (Ca + Ce)
- Leaf modules should be ~1.0, core ~0.0
- Flag Zone of Pain drift (high stability + low abstractness)

## Output

1. **Violations** — must fix before commit (forbidden deps, circular imports,
   thick controllers).
2. **Warnings** — address soon (Zone of Pain drift, cohesion issues).
3. **OK** — no issues found.

For each violation: file, line, broken rule, suggested fix.

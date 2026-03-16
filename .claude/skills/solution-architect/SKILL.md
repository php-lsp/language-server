---
name: solution-architect
description: Evaluate architecture health, detect structural issues, propose and implement refactoring solutions. Analyzes coupling, cohesion, dependency flow, Zone of Pain modules, and missing patterns. Use for architectural audits and refactoring decisions.
disable-model-invocation: false
allowed-tools: Read, Glob, Grep, Edit, Write, Bash, Agent
---

# Skill: Solution Architect

## Goal

Perform a comprehensive architectural audit of the PHP Language Server codebase.
Identify structural issues, propose concrete solutions, and implement fixes that
improve modularity, scalability, and maintainability.

## Evaluation Framework

### 1. Dependency Flow Analysis

Scan all `use` statements in `app/` and build the actual dependency graph:

```
For each PHP file in app/:
  1. Extract namespace and all `use App\...` imports
  2. Map to module: App\Module\{Module}\... → Module
  3. Build adjacency list: Module → [dependencies]
  4. Compare against allowed dependencies (docs/architecture.md)
```

Flag:
- **Forbidden dependencies** — feature module → feature module
- **Infrastructure → feature** — e.g., Indexing importing from Completion
- **Circular dependencies** — A → B → A
- **Contracts depending on implementations** — Core/Contracts importing Module

### 2. Zone of Pain Detection

For each module, compute:
- **Ca** (afferent coupling) — how many other modules depend on this one
- **Ce** (efferent coupling) — how many modules this one depends on
- **Instability** I = Ce / (Ca + Ce)
- **Abstractness** A = interfaces / total classes
- **Distance** D = |A + I - 1|

Flag modules where D > 0.5 — they are far from the Main Sequence.

### 3. Controller Thickness Audit

For each controller in `app/Controller/`:
- Count lines of business logic (excluding imports, attributes, constructor)
- Flag controllers that contain: filtering, data transformation, direct
  storage access, complex conditionals
- Controllers should only: create context, invoke contributors, collect results

### 4. Missing Abstraction Detection

Identify concrete classes in stable modules that should be interfaces:
- Classes with Ca > 10 and no corresponding interface
- Value objects used across module boundaries without contracts
- Storage implementations used directly instead of via interfaces

### 5. Code Smell Detection

- Commented-out code (`dump()`, `echo`, `var_dump`, `print_r`)
- Dead code (unused classes, methods, imports)
- God classes (> 200 LOC with mixed responsibilities)
- Duplicated patterns that should be extracted

### 6. Missing Pattern Analysis

Check for patterns that should exist in a mature LSP:
- [ ] Request cancellation flow ($/cancelRequest → CancellationToken)
- [ ] Incremental file updates (didChange → partial reindex)
- [ ] Background task management (indexing progress)
- [ ] Error recovery in contributors
- [ ] Consistent parallel execution across controllers

## Implementation Phase

After identifying issues, implement fixes in priority order:

1. **Critical** — anything blocking core functionality
2. **High** — performance issues, missing abstractions for stable modules
3. **Medium** — code smells, inconsistencies
4. **Low** — style issues, minor improvements

For each fix:
- Create or modify the minimum set of files
- Ensure all existing tests still pass
- Add tests for new abstractions if needed
- Follow the project's code style (Mago formatter)

## Output Format

```
## Architecture Audit Results

### Violations (must fix)
- [file:line] Description → Fix applied

### Warnings (should fix)
- [file:line] Description → Recommendation

### Metrics Update
- Module metrics table (Ca, Ce, I, A, D)
- Comparison with previous analysis

### Changes Made
- List of files modified with rationale
```

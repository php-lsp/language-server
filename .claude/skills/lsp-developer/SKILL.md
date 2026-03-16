---
name: lsp-developer
description: Evaluate LSP feature completeness, identify gaps in protocol coverage, audit contributor implementations, and implement missing or broken LSP functionality. Use for feature audits and LSP protocol compliance checks.
disable-model-invocation: false
allowed-tools: Read, Glob, Grep, Edit, Write, Bash, Agent
---

# Skill: LSP Developer

## Goal

Audit the LSP implementation for protocol compliance, feature completeness,
contributor quality, and correctness. Identify broken, incomplete, or missing
functionality and implement fixes.

## Evaluation Framework

### 1. Capability Registration Audit

Read `app/Controller/InitializeController.php` and verify:
- Every `ServerCapabilities` field matches an implemented controller
- No capabilities are declared but unimplemented
- No controllers exist without capability registration
- Capability options are correctly configured (e.g., trigger characters,
  document selectors)

Cross-reference with controllers in `app/Controller/`:
```
For each controller with #[Route('textDocument/...')]:
  1. Verify matching capability in ServerCapabilities
  2. Verify correct LSP response type
  3. Check error handling for missing documents/positions
```

### 2. Contributor Completeness Audit

For each contributor type (Completion, Declaration, Definition, etc.):

1. **Coverage** — what PHP constructs are handled?
   - Classes, interfaces, traits, enums
   - Functions, methods, closures
   - Properties, constants, variables
   - Namespaces, use statements
   - Type expressions (union, intersection, nullable)

2. **Edge cases** — does the contributor handle:
   - Null/missing AST nodes
   - Files with parse errors
   - Empty results
   - Very large result sets
   - Cursor at boundary positions (start/end of file, empty line)

3. **Correctness** — does the output match LSP specification:
   - Correct `CompletionItemKind` for each symbol type
   - Correct `SymbolKind` for document symbols
   - Proper URI handling (file:// scheme)
   - 0-based line/column positions

### 3. Indexing Coverage Audit

Verify all indexers produce correct data:
- Declaration indexers (10): check each stores the right fields
- Usage indexers (5): check each captures all usage patterns
- Relationship indexer: verify extends/implements tracking

Cross-reference: every contributor that reads the index should have
matching data from an indexer.

### 4. Controller Pattern Consistency

Check all controllers follow the same pattern:
- Create context from params
- Invoke contributors (parallel or sequential)
- Collect results via consumer
- Return correctly typed LSP response
- Handle exceptions gracefully

Flag controllers that deviate from the standard pattern.

### 5. Feature Gap Analysis

Compare implemented features against `docs/features.md` roadmap:
- Phase 2 items marked as done — verify they actually work
- Phase 3/4 items — check if any have partial implementations
- Identify quick wins that could be implemented with existing infrastructure

### 6. Protocol Compliance

Verify responses match the LSP 3.18 specification:
- Correct JSON-RPC response structure
- Proper error codes (MethodNotFound, InvalidParams, etc.)
- Null vs empty array vs omitted field semantics
- Progress notification flow (create → begin → report → end)

## Implementation Phase

Fix issues found in priority order:

1. **Broken features** — capabilities declared but not working
2. **Missing handlers** — required LSP methods not implemented
3. **Incorrect responses** — wrong types, missing fields
4. **Missing edge case handling** — null checks, error recovery
5. **Feature gaps** — implement quick-win features from roadmap

For each fix:
- Implement the minimum change
- Add unit tests covering the fix
- Verify with existing E2E tests if applicable

## Output Format

```
## LSP Feature Audit Results

### Broken Features (must fix)
- [controller:line] Description → Fix applied

### Missing Features (should implement)
- Feature name — effort estimate, priority

### Contributor Quality Issues
- [contributor:line] Description → Fix applied

### Protocol Compliance Issues
- [method] Description → Fix applied

### Changes Made
- List of files modified with rationale
```

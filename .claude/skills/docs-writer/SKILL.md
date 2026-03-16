---
name: docs-writer
description: Write and maintain LLM-optimized documentation. Ensures all docs are synchronized with code, use minimal tokens, and provide maximum information density for AI agents consuming the codebase. Use after code changes to update documentation.
disable-model-invocation: false
allowed-tools: Read, Glob, Grep, Edit, Write
---

# Skill: Documentation Writer for LLM Agents

## Goal

Write and maintain documentation that is optimized for consumption by LLM
agents (Claude, GPT, Copilot). Every sentence must carry information.
No filler, no marketing language, no repetition. Documentation is a
machine-readable specification, not a human tutorial.

## Core Principles

### 1. Information Density

- **Bad:** "In this section, we will discuss how the indexing system works
  and how it processes files in the project workspace."
- **Good:** "Indexing traverses project files on init, builds in-memory
  secondary indexes for O(1) field lookups."

### 2. Tables Over Prose

When describing multiple items with properties, ALWAYS use tables:

```markdown
| Indexer | Key | Data Type | Fields |
|---------|-----|-----------|--------|
| ClassIndexer | php.classes.fqn | ClassData | fqn, name, namespace |
```

### 3. Code Over Description

A 3-line code example replaces a paragraph of explanation:

```php
// Instead of: "The contributor pattern works by creating a class that
// implements the contributor interface and adding the registration
// attribute, which causes the DI container to discover it automatically."

#[AsCompletionContributor]
final class FooContributor implements CompletionContributor { ... }
// Auto-discovered via DI tag lsp.completionContributors
```

### 4. Exact Numbers

- "15 completion contributors" not "many completion contributors"
- "300-file FIFO cache" not "a cache of parsed files"
- "O(1) via secondary index" not "fast lookup"

### 5. Flat Structure

Maximum 3 heading levels. Deep nesting wastes tokens and context window.

## Documentation Map

| File | Purpose | Update Triggers |
|------|---------|-----------------|
| `CLAUDE.md` | Project overview, commands, structure, architecture, quality constraints | Any structural change |
| `README.md` | Installation, running, building, client setup | CLI/build changes |
| `docs/overview.md` | LSP protocol overview, IDE connection | Protocol changes |
| `docs/contributors.md` | Contributor system guide | New contributor types |
| `docs/architecture.md` | Application architecture and design | Module/layer changes |
| `docs/architecture-analysis.md` | Architecture metrics, weaknesses | After refactoring |
| `docs/features.md` | Feature roadmap, LSP capabilities | New features implemented |
| `docs/positions.md` | Position system (LSP vs php-parser) | Position handling changes |
| `docs/binary-builds.md` | Standalone binary builds | Build system changes |
| `docs/codespaces.md` | GitHub Codespaces setup | Dev environment changes |
| `docs/e2e-testing.md` | E2E testing guide | Test infrastructure changes |
| `docs/testing/coverage-plan.md` | Unit test coverage plan | Test coverage changes |
| `docs/type-system-guide.md` | Type system documentation | TypeSystem module changes |
| `docs/apm.md` | APM integration | Telemetry changes |
| `docs/debug-index-inspector.md` | Debug HTTP server | Debug controller changes |

## Review Steps

### 1. Detect Stale Documentation

For each code change made in the current session:

1. Map changed file → affected docs (use Documentation Map)
2. Read each affected doc
3. Identify stale sections:
   - Outdated counts (contributor counts, file counts, indexer counts)
   - Missing entries (new controllers, new contributors, new modules)
   - Wrong descriptions (changed behavior, renamed classes)
   - Dead references (deleted files, moved classes)

### 2. Update All Affected Docs

For each stale section:
- Update with current, accurate information
- Apply LLM-optimized style rules
- Verify cross-references are valid

### 3. Verify Consistency

After updates:
- All docs referenced in `CLAUDE.md` Guidelines must exist
- No orphan docs in `docs/` not referenced from `CLAUDE.md`
- Cross-references use relative paths
- Counts match reality (grep and count)

### 4. CLAUDE.md Special Rules

CLAUDE.md is the primary entry point for LLM agents. It MUST:
- Have accurate project structure tree
- Have correct contributor type table with DI tags
- Have up-to-date module descriptions with exact counts
- Have current common commands section
- Have complete key dependencies list
- Reference every doc file in Guidelines section

## Quality Checklist

For every doc file:
- [ ] Starts with single `# Title`
- [ ] No filler sentences
- [ ] No redundant definitions (defined once, referenced elsewhere)
- [ ] Tables for multi-item lists
- [ ] Code examples for non-obvious patterns
- [ ] Exact numbers, not vague quantities
- [ ] Maximum 3 heading levels
- [ ] No decorative formatting (excessive bold, emoji, horizontal rules)
- [ ] English only
- [ ] Consistent terminology (contributor, module, controller)

## Output Format

```
## Documentation Update Results

### Updated Files
- [file] Section updated — reason

### New Sections Added
- [file] Section — why it was needed

### Verified Consistent
- List of docs checked and confirmed up-to-date

### Remaining Issues
- Any docs that need manual review
```

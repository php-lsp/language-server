---
name: review-docs
description: Review and synchronize documentation with code changes. Run after code changes, tests, and static analysis pass. Must run before /review-architecture.
disable-model-invocation: true
allowed-tools: Read, Glob, Grep, Edit, Write
---

# Review: Documentation

## Pipeline Position

Run after all code changes, tests, and static analysis pass — **before**
`/review-architecture`. The architecture skill reads documentation, so docs
must be up-to-date first.

## Goal

Keep all documentation synchronized with the codebase. Documentation is
primarily consumed by LLMs — every sentence must carry information, no filler.

## Documentation Map

| File | Describes |
|------|-----------|
| `CLAUDE.md` | Project overview, commands, structure, architecture, quality constraints |
| `README.md` | Installation, running, building, client setup |
| `docs/overview.md` | LSP protocol overview, IDE connection |
| `docs/contributors.md` | Contributor system guide |
| `docs/architecture.md` | Application architecture and design |
| `docs/architecture-analysis.md` | Architecture metrics, weaknesses, comparisons |
| `docs/features.md` | Feature roadmap, LSP capabilities checklist |
| `docs/positions.md` | Position system (LSP vs php-parser) |
| `docs/binary-builds.md` | Standalone binary builds |
| `docs/codespaces.md` | GitHub Codespaces setup |
| `docs/e2e-testing.md` | E2E testing guide |
| `docs/testing/coverage-plan.md` | Unit test coverage plan |
| `docs/type-system-guide.md` | Type system documentation |

## Review Steps

### 1. Detect Stale Documentation

Read the git diff of current changes. For each changed file:

- **Controller** added/removed/renamed — update `CLAUDE.md` (Project
  Structure, Architecture), `docs/architecture.md` (controller table).
- **Contributor** added/removed — update `CLAUDE.md` (module descriptions,
  contributor counts), `docs/contributors.md`, `docs/features.md`.
- **Module** added/removed/restructured — update `CLAUDE.md` (Project
  Structure), `docs/architecture.md`.
- **Contracts/interfaces** changed — update `CLAUDE.md` (contributor types
  table), `docs/contributors.md`.
- **Config** changed (`services.yaml`, `mago.toml`, `phpunit.xml`,
  `composer.json`) — update docs referencing those configs.
- **Commands** changed in `composer.json` scripts — update `CLAUDE.md`
  (Common Commands).
- **Dependencies** added/removed — update `CLAUDE.md` (Key Dependencies).
- **Test structure** changed — update `docs/e2e-testing.md`,
  `docs/testing/coverage-plan.md`.

### 2. Apply LLM-Optimized Style

- **No filler**: remove "In this section we will discuss...", "As mentioned
  above...", "It is worth noting that...". Start with facts.
- **No redundancy**: define a concept once, reference it elsewhere.
- **Tables over prose**: for lists of items with properties.
- **Code over description**: 3-line example > paragraph of explanation.
- **Flat structure**: max 3 heading levels. Deep nesting wastes tokens.
- **Exact counts**: update contributor/file counts when they change.
- **No decorative formatting**: no excessive bold, no emoji, no horizontal
  rules between every section.
- **Consistent terminology**: use codebase terms — contributor (not
  plugin/extension/handler), module (not package/bundle).

### 3. Structural Checks

- Every doc starts with a single `# Title`.
- Every doc referenced in `CLAUDE.md` Guidelines must exist.
- No orphan docs in `docs/` not referenced from `CLAUDE.md`.
- Cross-references use relative paths.

## Output

1. Fix all documentation issues (update files directly).
2. Report what was updated and why.
3. If nothing needs updating — state that explicitly.

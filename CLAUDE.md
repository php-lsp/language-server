# CLAUDE.md

## Project Overview

PHP Language Server Protocol (LSP) implementation — a modular server providing
code intelligence (completion, definition, declaration, hover, references,
rename, document highlight, formatting, diagnostics, folding ranges, inlay
hints, selection ranges) to IDEs and editors via the LSP standard.

- **Language:** PHP 8.4+
- **Framework:** php-lsp/kernel + Symfony DependencyInjection
- **Entry point:** `bin/lsp` (CLI), `app/Application.php` (kernel)
- **Autoload:** PSR-4 — `App\` → `app/`, `App\Tests\` → `tests/`
- **Status:** Pre-release

## Common Commands

```shell
# Run the server
php ./bin/lsp serve App\\Application --port=5007

# Tests
composer test              # Run all tests (unit + functional + e2e)
composer test:unit         # PHPUnit unit tests only
composer test:functional   # Functional tests (server boot)
composer test:e2e          # E2E playground tests (starts real LSP server)

# Code quality
composer mago:lint         # Mago linter (with baseline)
composer mago:analyze      # Mago analyzer (with baseline)
composer mago:fix          # Mago auto-fix (safe + potentially-unsafe)
composer mago:baseline     # Regenerate Mago baselines
composer mago:format       # Mago formatter (auto-fix)
composer mago:format:check # Mago formatter (dry-run check)

# Build
composer build:prod        # Compile PHAR → var/prod/build.phar
composer build:run:local   # Run compiled PHAR
```

## Project Structure

```
app/
├── Application.php            # Kernel — extends LanguageServerKernel
├── Controller/                # LSP request handlers (35 controllers, routes via #[Route] attributes)
│   ├── InitializeController.php
│   ├── InitializedController.php
│   ├── SetTraceController.php       # $/setTrace — trace level configuration
│   ├── CancelRequestController.php  # $/cancelRequest — request cancellation
│   ├── WorkspaceSymbolController.php  # workspace/symbol — project-wide symbol search
│   ├── Debug/                 # Debug HTTP server controllers (4): index list, get, search, keys
│   ├── TextDocument/          # textDocument/* method handlers (25 controllers)
│   │   ├── CodeActionController.php
│   │   ├── CompletionController.php
│   │   ├── DeclarationController.php
│   │   ├── DefinitionController.php
│   │   ├── DiagnosticController.php
│   │   ├── DocumentChangeController.php
│   │   ├── DocumentCloseController.php
│   │   ├── DocumentHighlightController.php
│   │   ├── DocumentOpenController.php
│   │   ├── DocumentSaveController.php
│   │   ├── DocumentSymbolController.php
│   │   ├── FoldingRangeController.php
│   │   ├── FormattingController.php
│   │   ├── HoverController.php
│   │   ├── ImplementationController.php
│   │   ├── InlayHintController.php
│   │   ├── PrepareRenameController.php
│   │   ├── PublishDiagnosticsController.php
│   │   ├── RangeFormattingController.php
│   │   ├── ReferencesController.php
│   │   ├── RenameController.php
│   │   ├── SelectionRangeController.php
│   │   ├── SemanticTokensController.php
│   │   ├── SignatureHelpController.php
│   │   └── TypeDefinitionController.php
│   └── Workspace/             # workspace/* method handlers
│       └── DidChangeWatchedFilesController.php
├── Core/UriHelper.php         # URI → file path conversion utility
├── Core/Cancellation/         # CancellationToken, CancellationTokenRegistry (with auto-eviction)
├── Core/Contracts/            # Plugin interfaces and attributes (19 directories)
│   ├── CodeAction/            # CodeActionContributor, AsCodeActionContributor
│   ├── Completion/            # CompletionContributor, AsCompletionContributor
│   ├── Declaration/           # DeclarationContributor, AsDeclarationContributor
│   ├── Definition/            # DefinitionContributor, AsDefinitionContributor
│   ├── Documentation/         # DocumentationContributor, AsDocumentationContributor
│   ├── FoldingRange/          # FoldingRangeContributor, AsFoldingRangeContributor
│   ├── Highlight/             # DocumentHighlightContributor, AsDocumentHighlightContributor
│   ├── Implementation/        # ImplementationContributor, AsImplementationContributor
│   ├── Indexing/              # IndexerInterface, AsIndexer
│   ├── InlayHint/             # InlayHintContributor, AsInlayHintContributor
│   ├── Notification/          # ProgressNotifierInterface
│   ├── PrefixMatcher/         # PrefixMatcher interface, StrContainsMatcher
│   ├── PsiFile/               # PsiFileInterface, PsiFileManagerInterface
│   ├── DocumentSymbol/        # DocumentSymbolContributor, AsDocumentSymbolContributor
│   ├── References/            # ReferenceContributor, AsReferenceContributor
│   ├── SelectionRange/        # SelectionRangeContributor, AsSelectionRangeContributor
│   ├── SemanticToken/         # SemanticTokenContributor, AsSemanticTokenContributor, SemanticTokenContext, SemanticTokenConsumer
│   ├── Signature/             # SignatureContributor, AsSignatureContributor
│   └── TypeDefinition/        # TypeDefinitionContributor, AsTypeDefinitionContributor
├── Module/                    # Feature implementations (24 modules)
│   ├── CodeAction/            # Code action contributors (2): import symbol, remove unused import
│   ├── Completion/            # Completion contributors (15): keywords, classes, functions, interfaces, traits, enums, constants, superglobals, shortcuts, class members, class constants, enum cases, use statements, namespaces, variables
│   ├── Debug/                 # Debug HTTP server (DebugHttpServer, DebugHtmlRenderer)
│   ├── Declaration/           # Declaration contributors (7): class, method, function, property, class constant, global constant, variable
│   ├── Definition/            # Definition contributors (4): class, function, method, variable
│   ├── Documentation/         # Documentation/hover contributors (7): docblock, nodes-trace, class, function, method, property, constant
│   ├── Document/              # Document loading and identification
│   ├── DocumentSymbol/        # Document symbol contributors (1): AST-based class/function/enum/trait symbols
│   ├── FoldingRange/          # Folding range contributors (3): AST nodes, comments, use blocks
│   ├── Highlight/             # Document highlight contributors (2): variable, name
│   ├── Implementation/        # Implementation contributors (1): interface/class implementations
│   ├── Indexing/              # Declaration indexers (10) + usage indexers (5) + relationship indexers (1) + file collection + storage + IndexData value objects
│   ├── InlayHint/             # Inlay hint contributors (1): parameter names at call sites
│   ├── Notification/          # Server notifications: progress (WorkDoneProgress), error messages, connection state
│   ├── PsiFile/               # AST parsing via nikic/php-parser
│   ├── References/            # Reference contributors (7): class, function, method, property, variable, interface, class constant
│   ├── SelectionRange/        # Selection range contributors (1): AST-based smart selection
│   ├── SemanticToken/         # Semantic token support: AstSemanticTokenContributor, RawSemanticToken, SemanticTokenLegend, SemanticTokenEncoder
│   ├── Signature/             # Signature contributors (3): function, method, constructor
│   ├── Telemetry/             # APM tracing (OpenTelemetry + SigNoz)
│   ├── TypeDefinition/        # Type definition contributors (1): type-aware navigation via TypeResolver
│   ├── TypeSystem/            # PHPStan-based type resolution (TypeResolver, PHPStanBootstrap)
│   └── Workspace/             # Workspace/project management
├── DependencyInjection/       # HydratorCompilerPass for JSON-RPC serialization
├── Infrastructure/Symfony/    # LSPCompilerPass, DocumentManagerCompilerPass for DI
└── Listener/                  # Server event listeners (9): server, logger, message, connection, debug, document cache, incremental index, request tracing, error notification
config/
├── services.yaml              # Main DI config (imports services/*.yaml)
└── services/                  # controllers.yaml, listeners.yaml, logger.yaml, telemetry.yaml
docker/
└── signoz/                    # Docker Compose for SigNoz APM
tests/
├── Unit/                      # PHPUnit unit tests
├── Functional/                # Functional tests (server boot)
├── Playground/                # E2E tests (starts real LSP server)
├── Benchmark/                 # Performance benchmarks
└── Support/                   # Test utilities and helpers
.env                           # Default environment variables (committed)
.env.example                   # Environment variable reference template
```

## Architecture

The server uses a **contributor/plugin pattern**. Controllers receive LSP
requests, create a context object, and fan out to multiple contributors that
run in parallel (via React promises with timeouts).

**Request flow:** LSP Client → Controller → Context → Contributors (parallel) → Consumer → Response

### Adding a new contributor

1. Create a class implementing the contributor interface (e.g., `CompletionContributor`)
2. Add the registration attribute (e.g., `#[AsCompletionContributor]`)
3. Place it under `app/Module/` — Symfony DI auto-discovers it

Available contributor types and their DI tags:

| Attribute                    | Tag                            |
|------------------------------|--------------------------------|
| `#[AsCodeActionContributor]` | `lsp.codeActionContributors`   |
| `#[AsCompletionContributor]` | `lsp.completionContributors`   |
| `#[AsDeclarationContributor]`| `lsp.declarationContributors`  |
| `#[AsDefinitionContributor]` | `lsp.definitionContributors`   |
| `#[AsDocumentationContributor]`| `lsp.documentationContributors`|
| `#[AsDocumentHighlightContributor]`| `lsp.documentHighlightContributors`|
| `#[AsDocumentSymbolContributor]`| `lsp.documentSymbolContributors`|
| `#[AsFoldingRangeContributor]`| `lsp.foldingRangeContributors` |
| `#[AsImplementationContributor]`| `lsp.implementationContributors`|
| `#[AsInlayHintContributor]`  | `lsp.inlayHintContributors`    |
| `#[AsReferenceContributor]`  | `lsp.referenceContributors`    |
| `#[AsSelectionRangeContributor]`| `lsp.selectionRangeContributors`|
| `#[AsSemanticTokenContributor]`| `lsp.semanticTokenContributors`|
| `#[AsSignatureContributor]`  | `lsp.signatureContributors`    |
| `#[AsTypeDefinitionContributor]`| `lsp.typeDefinitionContributors`|
| `#[AsIndexer]`               | `lsp.indexers`                 |

## Code Style

- **Formatter:** Mago (PER-CS 2.0 — default preset)
- **Config:** `mago.toml` `[formatter]` section
- Single quotes, trailing commas, imports sorted alphabetically
- Always run `composer mago:format` before committing

## Static Analysis

- **Mago** — fast PHP linter and analyzer (written in Rust)
- Config: `mago.toml`
- Baselines: `mago-lint-baseline.toml`, `mago-analysis-baseline.toml`
- Analyzes: `app/` directory only
- Run `composer mago:baseline` to regenerate baselines after fixing existing issues

## Key Dependencies

- `php-lsp/kernel` — LSP server framework
- `php-lsp/protocol` — LSP protocol type definitions
- `nikic/php-parser` — PHP AST parsing
- `php-lsp/bridge-server-react` — Async I/O via ReactPHP
- `php-lsp/ext-document-manager` — Document lifecycle management
- `monolog/monolog` — Logging
- `phpstan/phpstan` — PHPStan for type resolution (used by TypeSystem module)
- `carthage-software/mago` — Mago PHP linter, analyzer, and formatter
- `open-telemetry/sdk` + `open-telemetry/exporter-otlp` — APM tracing via OpenTelemetry
- `symfony/dotenv` — `.env` file loading for environment variable management

## Quality Constraints

These constraints are **mandatory**. Work is **not considered complete** until
all of them pass. If any check fails — fix the issues and re-run everything.

### 1. Tests must pass

```shell
composer test
```

**All** tests must pass — unit, functional, and E2E. Always run `composer test`
(not just `composer test:unit`). Never finish work with failing tests.

### 2. Static analysis must pass (Mago)

```shell
composer mago:lint         # Linter (with baseline)
composer mago:analyze      # Analyzer (with baseline)
```

Fix all new issues reported by Mago. Do **not** regenerate baselines to hide
new issues — only run `composer mago:baseline` when intentionally resolving
existing baseline entries.

### 3. Code style must pass (Mago)

```shell
composer mago:format       # Auto-fix formatting
composer mago:format:check # Verify (dry-run)
```

Always run `composer mago:format` before committing. Verify with
`composer mago:format:check`.

### 4. Code coverage >= 80% for new features

When writing new features, code coverage must be at least 80%:

```shell
php -dpcov.enabled=1 vendor/bin/phpunit --coverage-text
```

If coverage is below 80%, write additional tests.

### 5. CI must be green

After pushing, verify that **all** GitHub Actions workflows pass:

- **tests** — all tests: unit, functional, E2E (PHP 8.4 + 8.5, ubuntu + windows)
- **mago** — lint + analyze
- **codestyle** — `mago format --check`
- **coverage** — code coverage report
- **security** — `composer audit`
- **release** — standalone binary builds (triggered on version tags only)

Check CI status with `gh run list` or in the PR. If any workflow fails,
fix the issue locally and push again. Work is not done until CI is fully green.

### Workflow

After completing any code change:

1. `composer mago:fix` — auto-fix lint issues
2. `composer mago:format` — auto-fix formatting
3. `composer mago:lint` — verify linter passes
4. `composer mago:analyze` — verify analyzer passes
5. `composer mago:format:check` — verify formatting
6. `composer test` — verify **all** tests pass (unit + functional + E2E)
7. For new features: check coverage >= 80%
8. `/review-docs` — synchronize documentation with code changes
9. `/review-architecture` — check dependency violations and coupling
10. Push and verify all CI workflows are green

If **any** step fails — fix and repeat from step 1.

## Review Skills

Custom slash commands for code review. Run in order after quality checks pass.

| Skill | Command | When to Run | Purpose |
|-------|---------|-------------|---------|
| Tests | `/review-tests` | When writing or fixing tests | Enforce test doubles, structure, coverage rules |
| Docs | `/review-docs` | After tests and static analysis | Sync docs with code changes, LLM-optimized style |
| Architecture | `/review-architecture` | Last, after `/review-docs` | Detect dependency violations, layer boundary crossings |

Pipeline order: code changes → mago fix/format → lint/analyze → tests →
`/review-tests` (if writing tests) → `/review-docs` → `/review-architecture` → push.

Skill definitions: [.claude/skills/](.claude/skills/)

## Guidelines

- **All documentation must be written in English.** This applies to all
  files in `docs/`, README.md, CLAUDE.md, code comments, and commit messages.
- When you modify code that is described in this file or any documentation
  file (README.md, docs/, etc.), you **must** update the relevant
  documentation to reflect those changes. Keep docs in sync with code.
- See [README.md](README.md) for installation, running, building, and
  client setup instructions.
- See [docs/overview.md](docs/overview.md) for LSP protocol overview and
  IDE connection guide.
- See [docs/contributors.md](docs/contributors.md) for the contributor
  system guide (how to create and register contributors).
- See [docs/architecture.md](docs/architecture.md) for the application
  architecture and design documentation.
- See [config/services.yaml](config/services.yaml) and subdirectories for
  service registration details.
- See [mago.toml](mago.toml) for Mago linter, analyzer, and formatter configuration.
- See [phpunit.xml](phpunit.xml) for test configuration.
- See [docs/codespaces.md](docs/codespaces.md) for GitHub Codespaces setup
  and troubleshooting guide.
- See [docs/features.md](docs/features.md) for the feature roadmap, LSP
  capabilities checklist, and implementation priorities.
- See [docs/binary-builds.md](docs/binary-builds.md) for standalone binary
  builds via php-micro, release workflow, and platform support.
- See [docs/architecture-analysis.md](docs/architecture-analysis.md) for the
  objective architecture analysis, metrics, weakness assessment, and comparison
  with other LSP implementations.
- See [docs/positions.md](docs/positions.md) for position system documentation
  (LSP 0-based vs php-parser 1-based, byte offsets, conversion helpers).
- See [docs/debug-index-inspector.md](docs/debug-index-inspector.md) for the
  debug HTTP server (index browser, global search, batch actions, JSON API).
- See [docs/e2e-testing.md](docs/e2e-testing.md) for end-to-end testing guide
  (playground workspace, server lifecycle, LSP client).
- See [docs/apm.md](docs/apm.md) for APM integration (OpenTelemetry + SigNoz),
  tracing configuration, and Docker setup.
- See [docs/type-system-guide.md](docs/type-system-guide.md) for the type
  system implementation guide (PHPStan integration, TypeResolver usage).
- See [docs/testing/coverage-plan.md](docs/testing/coverage-plan.md) for the
  unit test coverage plan and testability tiers.
- See [docs/expansion-plan.md](docs/expansion-plan.md) for the indexers,
  contributors, and providers expansion plan.
- See [docs/lsp-testing-strategy.md](docs/lsp-testing-strategy.md) for LSP
  testing strategy research and conformance approaches.

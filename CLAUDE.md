# CLAUDE.md

## Project Overview

PHP Language Server Protocol (LSP) implementation — a modular server providing
code intelligence (completion, definition, declaration, hover, references,
rename, document highlight, formatting, diagnostics) to IDEs and editors via
the LSP standard.

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
composer test              # Run all tests
composer test:unit         # PHPUnit unit tests

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
├── Controller/                # LSP request handlers (routes via #[Route] attributes)
│   ├── InitializeController.php
│   ├── WorkspaceSymbolController.php  # workspace/symbol — project-wide symbol search
│   └── TextDocument/          # textDocument/* method handlers
│       ├── CompletionController.php
│       ├── DeclarationController.php
│       ├── DefinitionController.php
│       ├── DocumentHighlightController.php
│       ├── DocumentSymbolController.php
│       ├── FormattingController.php
│       ├── HoverController.php
│       ├── RenameController.php
│       ├── PrepareRenameController.php
│       ├── DiagnosticController.php
│       └── ...
├── Core/Contracts/            # Plugin interfaces and attributes
│   ├── Completion/            # CompletionContributor, AsCompletionContributor
│   ├── Declaration/           # DeclarationContributor, AsDeclarationContributor
│   ├── Definition/            # DefinitionContributor, AsDefinitionContributor
│   ├── Documentation/         # DocumentationContributor, AsDocumentationContributor
│   ├── Highlight/             # DocumentHighlightContributor, AsDocumentHighlightContributor
│   ├── Indexing/              # IndexerInterface, AsIndexer
│   ├── References/            # ReferenceContributor, AsReferenceContributor
│   ├── Signature/             # SignatureContributor, AsSignatureContributor
│   └── PrefixMatcher/         # PrefixMatcher interface, StrContainsMatcher
├── Module/                    # Feature implementations
│   ├── Completion/            # Completion contributors (15): keywords, classes, functions, interfaces, traits, enums, constants, superglobals, shortcuts, class members, class constants, enum cases, use statements, namespaces, variables
│   ├── Declaration/           # Declaration contributors (7): class, method, function, property, class constant, global constant, variable
│   ├── Definition/            # Definition contributors (4): class, function, method, variable
│   ├── Documentation/         # Documentation/hover contributors (7): docblock, nodes-trace, class, function, method, property, constant
│   ├── Highlight/             # Document highlight contributors (2): variable, name
│   ├── References/            # Reference contributors (7): class, function, method, property, variable, interface, constant
│   ├── Signature/             # Signature contributors (3): function, method, constructor
│   ├── Indexing/              # Declaration indexers (11) + usage indexers (5) + storage + IndexData value objects
│   ├── PsiFile/               # AST parsing via nikic/php-parser
│   ├── Document/              # Document loading and identification
│   ├── Workspace/             # Workspace/project management
│   ├── TypeSystem/            # PHPStan-based type resolution (TypeResolver, PHPStanBootstrap)
│   └── Notification/          # Server notification sender
├── Infrastructure/Symfony/    # LSPCompilerPass for DI
└── Listener/                  # Server, logger, message event listeners
config/
├── services.yaml              # Main DI config (imports services/*.yaml)
└── services/                  # controllers.yaml, listeners.yaml, logger.yaml
tests/
└── Unit/                      # PHPUnit unit tests
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
| `#[AsCompletionContributor]` | `lsp.completionContributors`   |
| `#[AsDeclarationContributor]`| `lsp.declarationContributors`  |
| `#[AsDefinitionContributor]` | `lsp.definitionContributors`   |
| `#[AsDocumentationContributor]`| `lsp.documentationContributors`|
| `#[AsDocumentHighlightContributor]`| `lsp.documentHighlightContributors`|
| `#[AsReferenceContributor]`  | `lsp.referenceContributors`    |
| `#[AsSignatureContributor]`  | `lsp.signatureContributors`    |
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

## Quality Constraints

These constraints are **mandatory**. Work is **not considered complete** until
all of them pass. If any check fails — fix the issues and re-run everything.

### 1. Tests must pass

```shell
composer test
```

All unit tests must pass. Never finish work with failing tests.

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

- **tests** — unit tests (PHP 8.4 + 8.5, ubuntu + windows)
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
6. `composer test` — verify all tests pass
7. For new features: check coverage >= 80%
8. Push and verify all CI workflows are green

If **any** step fails — fix and repeat from step 1.

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

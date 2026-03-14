# CLAUDE.md

## Project Overview

PHP Language Server Protocol (LSP) implementation — a modular server providing
code intelligence (completion, declarations, hover, references, rename,
diagnostics) to IDEs and editors via the LSP standard.

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
composer test              # Run all tests (unit + feature)
composer test:unit         # PHPUnit only
composer test:feature      # Behat only

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
│   └── TextDocument/          # textDocument/* method handlers
│       ├── CompletionController.php
│       ├── DeclarationController.php
│       ├── HoverController.php
│       ├── DiagnosticController.php
│       └── ...
├── Core/Contracts/            # Plugin interfaces and attributes
│   ├── Completion/            # CompletionContributor, AsCompletionContributor
│   ├── Declaration/           # DeclarationContributor, AsDeclarationContributor
│   ├── Documentation/         # DocumentationContributor, AsDocumentationContributor
│   ├── Indexing/              # IndexerInterface, AsIndexer
│   ├── References/            # ReferenceContributor, AsReferenceContributor
│   ├── Signature/             # SignatureContributor, AsSignatureContributor
│   └── PrefixMatcher/         # PrefixMatcher interface, StrContainsMatcher
├── Module/                    # Feature implementations
│   ├── Completion/            # Keyword, class, function, superglobal, shortcut contributors (5)
│   ├── Declaration/           # Class, method, function declaration contributors (3)
│   ├── Documentation/         # Docblock and node-trace contributors (2)
│   ├── References/            # Class, function, method, property, variable, interface, constant reference contributors (7)
│   ├── Signature/             # Function, method, constructor signature contributors (3)
│   ├── Indexing/              # Declaration indexers (5) + usage indexers (5) + storage
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
├── Unit/                      # PHPUnit tests
├── Feature/                   # Behat feature files
└── Context/                   # Behat contexts (Assert/, Provider/, Support/)
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
| `#[AsDocumentationContributor]`| `lsp.documentationContributors`|
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

## Pre-commit Checklist

Before every commit you **must** run the following commands and ensure they
pass without errors:

```shell
composer mago:format       # Auto-fix code formatting
composer mago:lint         # Run Mago linter (must pass)
composer mago:analyze      # Run Mago analyzer (must pass)
```

If linter or analyzer report new issues that are not in the baseline, fix
them before committing. Do **not** regenerate baselines to hide new issues —
only run `composer mago:baseline` when intentionally resolving existing
baseline entries.

## Guidelines

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
- See [phpunit.xml](phpunit.xml) and [behat.yaml](behat.yaml) for test
  configuration.
- See [docs/codespaces.md](docs/codespaces.md) for GitHub Codespaces setup
  and troubleshooting guide.

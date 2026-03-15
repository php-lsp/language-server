# Standalone Binary Builds

The LSP server can be distributed as a standalone executable that **does not
require PHP** to be installed on the target machine. This is achieved by
combining a [php-micro](https://github.com/static-php/phpmicro) SAPI with the
application PHAR archive into a single self-contained binary.

---

## How It Works

The build pipeline has three stages:

1. **PHAR compilation** — [Box](https://github.com/box-project/box) packages
   the application (`app/`, `config/`, `vendor/`) into `var/prod/build.phar`.
2. **micro.sfx compilation** —
   [static-php-cli (SPC)](https://github.com/crazywhalecc/static-php-cli) builds
   a statically-linked PHP interpreter with the `micro` SAPI and only the
   extensions the server needs (`phar`, `iconv`, `mbstring`, `tokenizer`,
   `filter`, `ffi`).
3. **Combination** — `spc micro:combine` concatenates `micro.sfx` with the PHAR,
   producing a single executable.

The resulting binary is a fully self-contained PHP interpreter + application.
Users download it, make it executable, and run it — no PHP, Composer, or
extension setup required.

```
┌─────────────────────────┐
│      micro.sfx          │  ← statically-linked PHP 8.4 interpreter
├─────────────────────────┤
│      build.phar         │  ← compressed application code + vendor
└─────────────────────────┘
         ↓
    ./php-lsp-linux-x86_64   ← single executable
```

---

## Supported Platforms

| Platform           | Architecture | Artifact name                  |
|--------------------|-------------|-------------------------------|
| Linux              | x86_64      | `php-lsp-linux-x86_64`        |
| macOS (Intel)      | x86_64      | `php-lsp-darwin-x86_64`       |
| macOS (Apple Silicon) | aarch64  | `php-lsp-darwin-aarch64`      |
| Windows            | x86_64      | `php-lsp-windows-x86_64.exe`  |

---

## Downloading a Release

Pre-built binaries are published as GitHub Release assets whenever a version tag
is pushed. Go to the
[Releases](https://github.com/php-lsp/language-server/releases) page and
download the archive for your platform.

### Linux / macOS

```bash
# Download (example for Linux x86_64, replace with your platform)
curl -fsSL -o php-lsp.tar.gz \
  https://github.com/php-lsp/language-server/releases/latest/download/php-lsp-linux-x86_64.tar.gz

# Extract
tar -xzf php-lsp.tar.gz

# Run
./php-lsp-linux-x86_64 serve App\\Application --port=5007
```

### Windows

Download `php-lsp-windows-x86_64.zip` from the release page, extract, and run:

```powershell
.\php-lsp-windows-x86_64.exe serve App\Application --port=5007
```

---

## Creating a Release

To trigger a new release build, push a version tag:

```bash
git tag 1.0.0
git push origin 1.0.0
```

The `release` GitHub Actions workflow (`.github/workflows/release.yml`) will:

1. Build the PHAR on each platform
2. Download and compile `micro.sfx` via SPC for each OS/architecture
3. Combine PHAR + micro.sfx into a standalone binary
4. Package as `.tar.gz` (Linux/macOS) or `.zip` (Windows)
5. Create a GitHub Release with auto-generated release notes and all artifacts
   attached

### Tag Format

Any tag matching `*.*.*` (semver) triggers the workflow. Examples: `1.0.0`,
`2.1.3`, `0.5.0`.

---

## Local Build

You can reproduce the build locally (Linux x86_64 only, matching `bin/prepare`):

```bash
# 1. Install SPC and build micro.sfx
composer build:prepare

# 2. Build PHAR + combine with micro.sfx
composer build:prod

# 3. Run the standalone binary
./var/prod/build serve App\\Application --port=5007
```

For other platforms, use the GitHub Actions workflow or install SPC manually for
your OS — see the [static-php-cli docs](https://static-php.dev/).

---

## PHP Extensions Included

The binary is compiled with a minimal set of extensions required by the server:

| Extension   | Purpose                                    |
|-------------|-------------------------------------------|
| `phar`      | PHAR archive support (embedded app)        |
| `iconv`     | Character encoding conversion              |
| `mbstring`  | Multibyte string handling                  |
| `tokenizer` | PHP tokenization (used by php-parser)      |
| `filter`    | Input filtering and validation             |
| `ffi`       | Foreign Function Interface (type system)   |

To add more extensions, update `SPC_EXTENSIONS` in
`.github/workflows/release.yml` and the extension list in `bin/prepare`.

---

## Troubleshooting

### Binary crashes on startup

Ensure you downloaded the correct architecture. On macOS, check with:

```bash
file ./php-lsp-darwin-aarch64
# Expected: Mach-O 64-bit executable arm64
```

### Permission denied (Linux/macOS)

```bash
chmod +x ./php-lsp-linux-x86_64
```

### Windows Defender / Gatekeeper warning

The binary is unsigned. On macOS, remove the quarantine attribute:

```bash
xattr -d com.apple.quarantine ./php-lsp-darwin-aarch64
```

On Windows, click "More info" → "Run anyway" in the SmartScreen dialog.

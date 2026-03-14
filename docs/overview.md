# PHP Language Server — Overview

## What is a Language Server?

A Language Server is a separate process that provides IDEs and code editors
with intelligent features for a specific programming language: code completion,
go-to-definition, find references, refactoring, error diagnostics, and more.

Without a Language Server, each editor must implement support for each language
independently. This leads to duplicated effort — N editors x M languages =
N*M implementations. The Language Server Protocol (LSP) solves this by acting
as a universal contract: one server per language works with every editor that
supports the protocol.

```
┌──────────────┐          LSP (JSON-RPC)          ┌──────────────────┐
│   VSCode     │◄────────────────────────────────►│                  │
├──────────────┤                                  │  PHP Language    │
│   Neovim     │◄────────────────────────────────►│  Server          │
├──────────────┤                                  │                  │
│   Sublime    │◄────────────────────────────────►│  (this project)  │
├──────────────┤                                  │                  │
│   PhpStorm   │◄────────────────────────────────►│                  │
└──────────────┘                                  └──────────────────┘
```

## Language Server Protocol (LSP)

LSP is an open protocol developed by Microsoft to standardize communication
between an IDE (client) and a language server. Communication happens over
**JSON-RPC 2.0** — a text-based request-response protocol.

### Transport

The client and server communicate over a TCP connection. The server starts as
a separate process and listens on a specified port:

```shell
php ./bin/lsp serve App\\Application --port=5007
```

Once connected, the client and server exchange JSON-RPC messages of three types:

| Message Type     | Description                                          |
|------------------|------------------------------------------------------|
| **Request**      | A request from the client expecting a Response       |
| **Response**     | The server's reply to a Request                      |
| **Notification** | A one-way message with no reply (sent in either direction) |

### Connection Lifecycle

```
Client                               Server
  │                                     │
  │──── initialize ────────────────────►│  Client sends its capabilities
  │◄─── InitializeResult ──────────────│  Server responds with its capabilities
  │                                     │
  │──── initialized ──────────────────►│  Client confirms readiness
  │                                     │
  │  ═══ Working Session ═══════════════│
  │                                     │
  │──── textDocument/completion ──────►│  Completion request
  │◄─── CompletionItem[] ─────────────│  List of suggestions
  │                                     │
  │──── textDocument/hover ───────────►│  Hover request
  │◄─── Hover ─────────────────────────│  Documentation at cursor
  │                                     │
  │──── textDocument/declaration ─────►│  Go-to-definition request
  │◄─── Location[] ───────────────────│  Definition location
  │                                     │
  │──── shutdown ─────────────────────►│  Shutdown request
  │◄─── null ──────────────────────────│
  │──── exit ──────────────────────────►│  Terminate process
  │                                     │
```

### Capabilities

During initialization, the server tells the client which features it supports.
Current capabilities of this Language Server:

| Capability             | Description                                           |
|------------------------|-------------------------------------------------------|
| **Completion**         | Code completion (classes, functions, keywords, superglobals) |
| **Hover**              | Documentation on cursor hover                         |
| **Declaration**        | Go to symbol definition                               |
| **References**         | Find all usages of a symbol                           |
| **Rename**             | Rename symbol with prepare support                    |
| **Signature Help**     | Function parameter hints                              |
| **Diagnostics**        | Error and warning reports                             |
| **Workspace Folders**  | Multi-root workspace support                          |
| **Text Document Sync** | Incremental document synchronization                  |

## Connecting to an IDE

### VSCode

A client extension is provided in the `client/vscode/` directory:

```shell
cd client/vscode
npm install
code .
# Press F5 to launch the extension
```

### Neovim (nvim-lspconfig)

```lua
local lspconfig = require('lspconfig')
local configs = require('lspconfig.configs')

configs.php_lsp = {
  default_config = {
    cmd = { 'php', './bin/lsp', 'serve', 'App\\Application', '--port=5007' },
    filetypes = { 'php' },
    root_dir = lspconfig.util.root_pattern('composer.json', '.git'),
  },
}

lspconfig.php_lsp.setup({})
```

### Other Editors

Any editor with LSP support can connect to the server via TCP:

1. Start the server: `php ./bin/lsp serve App\\Application --port=5007`
2. Configure the editor to connect to `tcp://127.0.0.1:5007`
3. Set file type filter: `php`

See [README.md](../README.md) for a full list of supported editors.

## Running and Building

### From Sources

```shell
php ./bin/lsp serve App\\Application --port=5007
```

### As a PHAR Archive

```shell
composer build:prod          # build
php var/prod/build.phar      # run
```

### Programmatically

```php
$app = new \App\Application('dev', true);
$app->listen('tcp://127.0.0.1:5007');
```

See [README.md](../README.md) for more build and run options.

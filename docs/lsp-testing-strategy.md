# LSP Server Testing Strategy

Research on approaches, tools, and best practices for testing Language Server Protocol implementations against the specification.

## Key Finding: No Official Conformance Suite

Microsoft [confirmed](https://github.com/Microsoft/language-server-protocol/issues/353) that **no standard conformance test suite exists** for LSP. The reasoning is that LSP defines the protocol (message formats, lifecycle, capabilities), but not expected output — completion items, hover content, diagnostics, etc. are entirely implementation-specific. Testing is the responsibility of each server's developers.

This means we need to build our own testing strategy combining multiple approaches.

## Testing Levels

### 1. Unit Tests (Handler-Level)

Test individual request handlers in isolation by mocking incoming LSP messages and asserting on the response structure.

**What to test:**
- Each handler returns the correct response type per the spec
- Error responses use the correct error codes
- Handler respects the server's declared capabilities

**In our project:** PHPUnit is already set up. Mock the incoming `RequestMessage` / `NotificationMessage` objects and call handlers directly.

### 2. Integration Tests (Protocol-Level)

Spawn the server as a subprocess, send real JSON-RPC messages over stdio, and validate responses.

**What to test:**
- Full lifecycle (initialize → initialized → requests → shutdown → exit)
- Content-Length framing in JSON-RPC
- Concurrent request handling
- Error responses for unknown methods

**In our project:** Behat is already configured. BDD scenarios can describe protocol interactions step by step.

### 3. End-to-End Tests (Client-Level)

Use a real or simulated LSP client to test the server in realistic conditions with actual workspace files.

**What to test:**
- Real-world editing workflows (open file, edit, get completions, go to definition)
- Multi-file workspace scenarios
- Performance under realistic workloads

## What to Validate Against the Spec

### Lifecycle Protocol

| Scenario | Expected Behavior |
|---|---|
| `initialize` request | Returns `InitializeResult` with server capabilities |
| `initialized` notification | Server begins normal operation |
| `shutdown` request | Returns success, server prepares to exit |
| Requests after `shutdown` | Must respond with error code `InvalidRequest` (-32600) |
| `exit` notification after `shutdown` | Server exits with code 0 |
| `exit` notification without `shutdown` | Server exits with code 1 |

### Capability Negotiation

- Server MUST only advertise capabilities it actually implements
- Server MUST NOT send requests/notifications requiring client capabilities the client did not declare
- Client capabilities received in `initialize` must be respected throughout the session

### Text Document Synchronization

- `textDocument/didOpen` — server tracks the document
- `textDocument/didChange` — correctly applies incremental or full updates based on declared `textDocumentSync` kind
- `textDocument/didClose` — server releases the document
- Version numbers must be tracked correctly

### Position Encoding

The LSP spec uses **UTF-16 offsets** by default for character positions. LSP 3.17 introduced `positionEncoding` capability negotiation. This is a common source of bugs — test with multi-byte characters (emoji, CJK, combining marks).

### Error Handling

- Unknown methods → `MethodNotFound` (-32601)
- Invalid JSON → `ParseError` (-32700)
- Invalid request object → `InvalidRequest` (-32600)
- Malformed params → `InvalidParams` (-32602)

### Specific Features (as implemented)

Each feature has spec-defined request/response shapes:

- **Completion** (`textDocument/completion`) — `CompletionList` or `CompletionItem[]`, trigger characters, resolve support
- **Hover** (`textDocument/hover`) — `Hover` with `MarkupContent` (plaintext or markdown)
- **Definition** (`textDocument/definition`) — `Location` | `Location[]` | `LocationLink[]`
- **References** (`textDocument/references`) — `Location[]`
- **Diagnostics** (`textDocument/publishDiagnostics`) — `Diagnostic[]` with severity, range, source, code
- **Rename** (`textDocument/rename`) — `WorkspaceEdit`
- **Formatting** (`textDocument/formatting`) — `TextEdit[]`

## Available Testing Tools

### pytest-lsp (Recommended for E2E)

Language-agnostic testing framework. Runs the server as a subprocess over stdio. Part of the [lsp-devtools](https://github.com/swyddfa/lsp-devtools) project.

```python
import pytest
import pytest_lsp
from pytest_lsp import ClientServerConfig, LanguageClient

@pytest_lsp.fixture(
    config=ClientServerConfig(
        server_command=["php", "bin/server.php"],
    ),
)
async def client(lsp_client: LanguageClient):
    response = await lsp_client.initialize_session(
        params={"capabilities": {}, "rootUri": "file:///tmp/workspace"}
    )
    yield
    await lsp_client.shutdown_session()

@pytest.mark.asyncio
async def test_initialize(client: LanguageClient):
    # If we get here, initialize/initialized succeeded
    assert client.server_capabilities is not None

@pytest.mark.asyncio
async def test_completions(client: LanguageClient):
    result = await client.text_document_completion_async(
        params={
            "textDocument": {"uri": "file:///tmp/workspace/test.php"},
            "position": {"line": 5, "character": 10},
        }
    )
    assert result is not None
```

Install: `pip install pytest-lsp`

### vscode-languageserver-protocol (Node.js)

The official LSP protocol library. Can be used to build a custom test client in TypeScript/JavaScript.

```typescript
import { createConnection, StreamMessageReader, StreamMessageWriter } from 'vscode-languageserver-protocol';
import { spawn } from 'child_process';

const server = spawn('php', ['bin/server.php']);
const connection = createConnection(
    new StreamMessageReader(server.stdout),
    new StreamMessageWriter(server.stdin)
);

// Send initialize request
const result = await connection.sendRequest('initialize', {
    capabilities: {},
    rootUri: 'file:///tmp/workspace',
});
```

Install: `npm install vscode-languageserver-protocol`

### lsp-devtools (Debugging & Inspection)

Provides tools for observing LSP traffic:

- **Agent** — proxy wrapper that intercepts messages between client and server
- **Inspect** — terminal UI for visualizing LSP traffic in real time
- **Record** — logs traffic to files or SQLite for post-hoc analysis

Useful during development and debugging, not for automated testing.

### lsp-test (Haskell)

Functional test framework. Powerful but requires Haskell toolchain. Used by haskell-language-server and ghcide.

### lsp-tester (Go)

Lightweight tool for basic protocol-level testing. Supports client mode (direct testing) and nexus mode (proxy between VS Code and server).

## Advanced: Fuzzing with LspFuzz

[LspFuzz](https://scholar.henryhc.net/files/publications/2025/ASE2025-LSPFuzz.pdf) is a grey-box fuzzer designed specifically for LSP servers. It mutates both source code and LSP operation sequences to find bugs at the intersection of parsing and request handling.

Results from evaluating 4 popular LSP servers:
- 51 previously unknown bugs found
- 42 confirmed by vendors
- 26 fixed
- 2 CVEs granted

Key insight: bugs often manifest from specific combinations of malformed/incomplete source code and particular LSP operations — testing them in isolation misses these.

## Recommended Strategy for This Project

### Phase 1: Unit Tests (PHPUnit)

- Test each handler method with mocked requests
- Verify response shapes match the spec
- Test error cases (invalid params, missing documents)

### Phase 2: Protocol Integration Tests (Behat)

Write Behat scenarios for protocol-level behavior:

```gherkin
Feature: LSP Lifecycle
  Scenario: Normal shutdown sequence
    Given the server is started
    When I send an initialize request
    Then I should receive InitializeResult with capabilities
    When I send an initialized notification
    And I send a shutdown request
    Then I should receive a successful response
    When I send an exit notification
    Then the server should exit with code 0

  Scenario: Exit without shutdown
    Given the server is started
    When I send an initialize request
    And I send an initialized notification
    And I send an exit notification
    Then the server should exit with code 1

  Scenario: Request after shutdown
    Given the server is started and initialized
    When I send a shutdown request
    And I send a hover request
    Then I should receive an InvalidRequest error
```

### Phase 3: E2E Tests (pytest-lsp)

- Add a Python test suite using pytest-lsp for realistic client-server interaction
- Test with actual PHP workspace files
- Validate completions, hover, diagnostics on real code

### Phase 4: Manual Smoke Testing

- Test with VS Code (primary target)
- Test with Neovim (via nvim-lspconfig)
- Verify no regressions with editor-specific quirks

## References

- [LSP Specification 3.17](https://microsoft.github.io/language-server-protocol/specifications/lsp/3.17/specification/)
- [LSP Specification 3.18](https://github.com/microsoft/language-server-protocol/blob/gh-pages/_specifications/lsp/3.18/specification.md)
- [VS Code Language Server Extension Guide](https://code.visualstudio.com/api/language-extensions/language-server-extension-guide)
- [GitHub Issue: Standard test suite?](https://github.com/Microsoft/language-server-protocol/issues/353)
- [pytest-lsp](https://pypi.org/project/pytest-lsp/)
- [lsp-devtools](https://github.com/swyddfa/lsp-devtools)
- [lsp-test (Haskell)](https://hackage.haskell.org/package/lsp-test)
- [lsp-tester (Go)](https://github.com/madkins23/lsp-tester)
- [vscode-languageserver-protocol (npm)](https://www.npmjs.com/package/vscode-languageserver-protocol)
- [vscode-languageserver-node (GitHub)](https://github.com/microsoft/vscode-languageserver-node)
- [LspFuzz Paper (ASE 2025)](https://scholar.henryhc.net/files/publications/2025/ASE2025-LSPFuzz.pdf)
- [Implementation Practices in LSP (MODELS 2022)](https://peldszus.com/wp-content/uploads/2022/08/2022-models-lspstudy.pdf)

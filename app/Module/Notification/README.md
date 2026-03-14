# Notification Module

Server-to-client notification system for sending LSP notifications
(e.g., progress updates, diagnostic results) back to the IDE.

## Classes

| Class | Description |
|-------|-------------|
| `ServerNotificationSender` | Sends JSON-RPC notifications to the connected LSP client |
| `Result` | Value object wrapping notification result data |

## Usage

`ServerNotificationSender` is used by:

- `InitializeController` — sends progress notifications during workspace indexing
- `InitializedController` — sends initialization confirmation notification
- `PublishDiagnosticsController` — forwards diagnostic notifications to the client
- `InMemoryPsiFileManager` — sends parse error diagnostics via `textDocument/publishDiagnostics`

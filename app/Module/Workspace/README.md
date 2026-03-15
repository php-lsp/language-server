# Workspace Module

Project and workspace management for the LSP server.

## Classes

| Class | Description |
|-------|-------------|
| `ProjectManager` | Holds the current project instance and provides access to it across the server |

## How It Works

During the `initialize` LSP request, the server receives workspace folders
from the client. `InitializeController` uses `ProjectFactoryInterface`
(from `php-lsp/workspace`) to create a project from the workspace folder URI
and stores it in `ProjectManager`.

The `ProjectManager` is then injected into indexers and other components that
need to traverse project files or access project configuration.

## Related Components

- `ProjectFactoryInterface` (from `php-lsp/workspace`) — creates project instances
- `FilesystemReaderFactory` (from `php-lsp/workspace`) — provides file system access for projects
- `InitializeController` — triggers project creation on LSP initialization

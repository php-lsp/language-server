# Document Module

Document loading and identification for the LSP server. Bridges the gap
between file URIs from the LSP protocol and actual file contents on disk.

## Classes

| Class | Description |
|-------|-------------|
| `DocumentLoaderInterface` | Contract for loading document contents from a URI |
| `LocalFileDocumentLoader` | Implementation that loads documents from the local filesystem |
| `DocumentIdentifierFactoryInterface` | Contract for creating document identifiers from URIs |
| `InMemoryDocumentIdentifierFactory` | In-memory implementation of document identifier creation |

## How It Works

The LSP protocol references files by URI (e.g., `file:///path/to/file.php`).
This module resolves those URIs to actual file content:

1. `DocumentIdentifierFactoryInterface` creates a typed identifier from the raw URI
2. `DocumentLoaderInterface` loads the file content using that identifier
3. The loaded document is used by `EditorInterface` (from `php-lsp/ext-document-manager`)
   for version tracking and incremental changes

## Service Registration

Both implementations are registered in `config/services/logger.yaml` as
concrete service bindings for their respective interfaces.

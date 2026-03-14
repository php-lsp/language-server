# Documentation Module

Hover documentation contributors for the `textDocument/hover` LSP method.
Contributors run **sequentially** and accumulate documentation strings.

## Contributors

| Class | Description |
|-------|-------------|
| `DocblockDocumentationContributor` | Extracts PHPDoc comments from the AST node at cursor position |
| `NodesTraceDocumentationContributor` | Builds a trace of AST nodes at the cursor for debugging/info display |

## Contract

- **Interface:** `App\Core\Contracts\Documentation\DocumentationContributor`
- **Attribute:** `#[AsDocumentationContributor]`
- **DI Tag:** `lsp.documentationContributors`
- **Context:** `DocumentationContext` — provides `textDocumentIdentifier`, `position`, `editor`
- **Consumer:** `DocumentationConsumer` — accumulates `string[]`

## How It Works

1. `HoverController` receives the LSP request
2. Creates a `DocumentationContext` from the request parameters
3. Runs all contributors **sequentially**, each appending documentation strings
4. Returns a `Hover` object with combined documentation to the client

## Adding a New Documentation Contributor

```php
#[AsDocumentationContributor]
final class MyDocContributor implements DocumentationContributor
{
    public function contribute(DocumentationContext $context, DocumentationConsumer $consumer): void
    {
        // ... extract documentation for the element at cursor ...
        $consumer('**My documentation** for this symbol');
    }
}
```

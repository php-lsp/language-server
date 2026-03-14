# Declaration Module

Go-to-definition contributors for the `textDocument/declaration` LSP method.
Contributors run **sequentially** and accumulate `Location[]` results.

## Contributors

| Class | Description |
|-------|-------------|
| `ClassDeclarationContributor` | Navigates to class definitions using the class index |
| `ClassMethodDeclarationContributor` | Navigates to method definitions within classes |
| `FunctionDeclarationContributor` | Navigates to function definitions using the function index |

## Contract

- **Interface:** `App\Core\Contracts\Declaration\DeclarationContributor`
- **Attribute:** `#[AsDeclarationContributor]`
- **DI Tag:** `lsp.declarationContributors`
- **Context:** `DeclarationContext` — provides `textDocumentIdentifier`, `position`, `editor`
- **Consumer:** `DeclarationConsumer` — accumulates `Location[]`

## How It Works

1. `DeclarationController` receives the LSP request
2. Creates a `DeclarationContext` from the request parameters
3. Runs all contributors **sequentially**, each adding results to a shared `DeclarationConsumer`
4. Returns `Location[]` to the client

## Adding a New Declaration Contributor

```php
#[AsDeclarationContributor]
final class MyDeclarationContributor implements DeclarationContributor
{
    public function contribute(DeclarationContext $context, DeclarationConsumer $consumer): void
    {
        // ... resolve location of the symbol at cursor ...
        $consumer(new Location(uri: $uri, range: $range));
    }
}
```

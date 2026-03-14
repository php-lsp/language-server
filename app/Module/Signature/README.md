# Signature Module

Function/method signature help contributors for the `textDocument/signatureHelp`
LSP method. Contributors run **sequentially** and provide parameter hints
when the cursor is inside a function call.

## Contributors

| Class | Description |
|-------|-------------|
| `FunctionSignatureContributor` | Shows parameter hints for global function calls (`func(▏)`) |
| `MethodSignatureContributor` | Shows parameter hints for method calls (`$obj->method(▏)`) |
| `ConstructorSignatureContributor` | Shows parameter hints for constructor calls (`new Foo(▏)`) |

## Contract

- **Interface:** `App\Core\Contracts\Signature\SignatureContributor`
- **Attribute:** `#[AsSignatureContributor]`
- **DI Tag:** `lsp.signatureContributors`
- **Context:** `SignatureContext` — provides `textDocumentIdentifier`, `position`, `editor`
- **Consumer:** `SignatureConsumer` — accumulates `SignatureInformation[]`

## How It Works

1. `SignatureHelpController` receives the LSP request
2. Creates a `SignatureContext` from the request parameters
3. Runs all contributors **sequentially**
4. Returns `SignatureHelp` with parameter info and active parameter index

## Adding a New Signature Contributor

```php
#[AsSignatureContributor]
final class MySignatureContributor implements SignatureContributor
{
    public function contribute(SignatureContext $context, SignatureConsumer $consumer): void
    {
        // ... resolve the function being called and its parameters ...
        $consumer(new SignatureInformation(
            label: 'myFunction(string $param1, int $param2)',
            parameters: [...],
        ));
    }
}
```

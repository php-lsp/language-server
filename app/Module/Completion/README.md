# Completion Module

Code completion contributors for the `textDocument/completion` LSP method.
Each contributor handles a specific category of completions and runs
**in parallel** with a 1-second timeout via React promises.

## Contributors

| Class | Description |
|-------|-------------|
| `ClassesCompletionContributor` | Completes class names from the index (`ClassIndexer`) using prefix matching |
| `FunctionCompletionContributor` | Completes function names from the index (`FunctionIndexer`) |
| `KeywordsCompletionContributor` | Completes PHP keywords and language constructs |
| `SuperglobalsCompletionContributor` | Completes PHP superglobal variables (`$_GET`, `$_POST`, etc.) |
| `ShortcutCompletionContributor` | Provides snippet-style completions (shortcuts/templates) |

## Supporting Classes

| Class | Description |
|-------|-------------|
| `KeywordDefinitions` | Static list of PHP keywords grouped by category |
| `KeywordCategory` | Enum defining keyword categories (statement, type, modifier, etc.) |

## Contract

- **Interface:** `App\Core\Contracts\Completion\CompletionContributor`
- **Attribute:** `#[AsCompletionContributor]`
- **DI Tag:** `lsp.completionContributors`
- **Context:** `CompletionContext` — provides `textDocumentIdentifier`, `position`, `editor`, `fileManager`, `currentNode()`
- **Consumer:** `CompletionConsumer` — accumulates `CompletionItem[]` with yield throttling (every 100 items or 10ms)

## How It Works

1. `CompletionController` receives the LSP request
2. Creates a `CompletionContext` from the request parameters
3. Launches all contributors **in parallel** via `React\Promise\all()`
4. Each contributor gets its own `CompletionConsumer` instance
5. Results are merged after all contributors complete (or timeout at 1s)
6. Returns `CompletionItem[]` to the client

## Adding a New Completion Contributor

```php
#[AsCompletionContributor]
final class MyCompletionContributor implements CompletionContributor
{
    public function contribute(CompletionContext $context, CompletionConsumer $consumer): void
    {
        $node = $context->currentNode();
        // ... generate completion items ...
        $consumer(new CompletionItem(label: 'example', kind: CompletionItemKind::ClassKind));
    }
}
```

Place the file in this directory — it will be auto-discovered via PSR-4 and the `#[AsCompletionContributor]` attribute.

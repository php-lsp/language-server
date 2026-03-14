# References Module

Find-all-usages contributors for the `textDocument/references` LSP method.
Contributors run **sequentially** and accumulate `Location[]` results.
Also used by `RenameController` for symbol rename operations.

## Contributors

| Class | Description |
|-------|-------------|
| `ClassReferenceContributor` | Finds all usages of a class (new, extends, implements, type hints, use) |
| `FunctionReferenceContributor` | Finds all usages of a function |
| `MethodReferenceContributor` | Finds all usages of a method (`->method()`, `::method()`) |
| `PropertyReferenceContributor` | Finds all usages of a property (`->prop`, `::$prop`) |
| `VariableReferenceContributor` | Finds all usages of a variable within its scope |
| `InterfaceReferenceContributor` | Finds all usages of an interface (implements, type hints) |
| `ClassConstantReferenceContributor` | Finds all usages of a class constant (`Foo::BAR`) |

## Contract

- **Interface:** `App\Core\Contracts\References\ReferenceContributor`
- **Attribute:** `#[AsReferenceContributor]`
- **DI Tag:** `lsp.referenceContributors`
- **Context:** `ReferenceContext` — provides `textDocumentIdentifier`, `position`, `editor`
- **Consumer:** `ReferenceConsumer` — accumulates `Location[]`

## Related Usage Indexers

Reference contributors rely on usage indexers from the `Indexing` module:

| Indexer | Key | Purpose |
|---------|-----|---------|
| `ClassUsageIndexer` | `php.classUsages` | Tracks class usage locations |
| `MethodCallUsageIndexer` | `php.methodCallUsages` | Tracks method call locations |
| `FunctionCallUsageIndexer` | `php.functionCallUsages` | Tracks function call locations |
| `PropertyAccessUsageIndexer` | `php.propertyAccessUsages` | Tracks property access locations |
| `ClassConstantUsageIndexer` | `php.classConstantUsages` | Tracks class constant usage locations |

## Adding a New Reference Contributor

```php
#[AsReferenceContributor]
final class MyReferenceContributor implements ReferenceContributor
{
    public function contribute(ReferenceContext $context, ReferenceConsumer $consumer): void
    {
        // ... find all usages of the symbol at cursor ...
        $consumer(new Location(uri: $uri, range: $range));
    }
}
```

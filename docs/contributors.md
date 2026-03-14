# Contributor System — Guide to Creating Contributors

## Concept

A Contributor is a modular unit of functionality responsible for a specific
aspect of language support. Instead of one monolithic handler per LSP method,
the system splits logic into many independent contributors that run in parallel.

For example, code completion is not implemented in a single class. Instead,
there are separate contributors for classes, functions, keywords, and
superglobal variables — each handles its own domain and knows nothing about
the others.

## Contributor Types

| Type | Interface | Attribute | DI Tag |
|------|-----------|-----------|--------|
| Code completion | `CompletionContributor` | `#[AsCompletionContributor]` | `lsp.completionContributors` |
| Go to definition | `DeclarationContributor` | `#[AsDeclarationContributor]` | `lsp.declarationContributors` |
| Documentation (hover) | `DocumentationContributor` | `#[AsDocumentationContributor]` | `lsp.documentationContributors` |
| Find references | `ReferenceContributor` | `#[AsReferenceContributor]` | `lsp.referenceContributors` |
| Function signatures | `SignatureContributor` | `#[AsSignatureContributor]` | `lsp.signatureContributors` |
| Indexing | `IndexerInterface` | `#[AsIndexer]` | `lsp.indexers` |

## Anatomy of a Contributor

Every contributor consists of three elements:

### 1. Interface

A contract defining the `contribute()` method. All interfaces follow the same
pattern — they accept a **context** and a **consumer**:

```php
interface CompletionContributor
{
    public function contribute(CompletionContext $context, CompletionConsumer $consumer): void;
}
```

### 2. Context

An object that provides the contributor with all the information about the
current request:

- `textDocumentIdentifier` — which file is being edited
- `position` — cursor position (line and column)
- `editor` — access to the contents of open documents
- `fileManager` — access to PSI files (AST trees)
- `currentNode()` — the AST node at the cursor position

```php
class CompletionContext
{
    public function __construct(
        public TextDocumentIdentifier $textDocumentIdentifier,
        public Position $position,
        public EditorInterface $editor,
        public InMemoryPsiFileManager $fileManager,
    ) {}

    public function currentNode(): ?Node
    {
        // Finds the AST node at the cursor position
    }
}
```

### 3. Consumer

A result accumulator object. The contributor invokes the consumer as a callable,
passing result items into it:

```php
class CompletionConsumer
{
    public array $results = [];

    public function __invoke(CompletionItem ...$items): void
    {
        foreach ($items as $item) {
            $this->results[] = $item;
        }
    }
}
```

The consumer is not just an array. It implements:

- **Limiting** — the maximum number of results can be capped
- **Cooperative multitasking** — periodically yields control to the event loop
  via `delay(0)` to avoid blocking other contributors

## How to Create a Contributor

### Step 1. Create a Class

Place the file in `app/Module/` under an appropriate directory:

```php
<?php

declare(strict_types=1);

namespace App\Module\Completion;

use App\Core\Contracts\Completion\AsCompletionContributor;
use App\Core\Contracts\Completion\CompletionConsumer;
use App\Core\Contracts\Completion\CompletionContext;
use App\Core\Contracts\Completion\CompletionContributor;
use Lsp\Protocol\Type\CompletionItem;
use Lsp\Protocol\Type\CompletionItemKind;

#[AsCompletionContributor]
final class MyCustomCompletionContributor implements CompletionContributor
{
    public function contribute(CompletionContext $context, CompletionConsumer $consumer): void
    {
        // Get the AST node at the cursor position
        $node = $context->currentNode();

        // Generate completion items
        $consumer(new CompletionItem(
            label: 'myFunction',
            kind: CompletionItemKind::FunctionKind,
            detail: 'My custom function',
        ));
    }
}
```

### Step 2. Done

No additional registration is needed. The system automatically:

1. Discovers the class via PSR-4 autoloading (`App\` namespace → `app/` directory)
2. Detects the `#[AsCompletionContributor]` attribute
3. Registers the service with the DI tag `lsp.completionContributors`
4. Injects it into `CompletionController` via `#[AutowireIterator]`

## Examples of Existing Contributors

### Class Completion

`ClassesCompletionContributor` — uses the index to find classes matching
the typed prefix:

```php
#[AsCompletionContributor]
final class ClassesCompletionContributor implements CompletionContributor
{
    public function __construct(
        private readonly IndexLookup $indexLookup,
    ) {}

    public function contribute(CompletionContext $context, CompletionConsumer $consumer): void
    {
        $element = $context->currentNode();
        $string = Tree::toString($element);
        $matcher = new StrContainsMatcher($string);

        foreach ($this->indexLookup->findByKey(ClassIndexer::class) as $key => $value) {
            if (!$matcher->match($value->value)) {
                continue;
            }

            $consumer(new CompletionItem(
                label: $value->value,
                kind: CompletionItemKind::ClassKind,
                detail: '[class]',
            ));
        }
    }
}
```

**What happens here:**
1. Gets the text at the cursor from the AST
2. Creates a matcher for filtering
3. Searches the index for classes matching the substring
4. Passes each match to the consumer

### Class Indexer

`ClassIndexer` — scans PHP files and builds an index of class FQNs:

```php
#[AsIndexer]
class ClassIndexer extends AbstractPhpIndexer
{
    public static function getKey(): string
    {
        return 'php.classes.fqn';
    }

    protected function indexInternal(PHPPsiFile $phpFile): array
    {
        $classes = Tree::childrenOfType($phpFile->ast, Class_::class);

        $results = [];
        foreach ($classes as $class) {
            $className = $class->namespacedName->toString();
            $results[$className] = $className;
        }

        return $results;
    }
}
```

## The Context → Contributor → Consumer Pattern

All contributor types follow the same flow:

```
Controller
    │
    ├── creates Context (from LSP request parameters)
    │
    ├── for each Contributor:
    │       │
    │       ├── creates Consumer (result accumulator)
    │       │
    │       └── calls contributor.contribute(context, consumer)
    │               │
    │               └── contributor adds results to consumer
    │
    └── collects results from all consumers → Response
```

In `CompletionController`, contributors run **in parallel** via React promises
with a 1-second timeout. If a contributor does not finish in time, its partial
results are still included in the response.

In `HoverController` and `DeclarationController`, contributors run
**sequentially** — each one adds to a shared consumer.

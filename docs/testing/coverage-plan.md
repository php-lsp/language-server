# Unit Test Coverage Plan

Goal: 100% code coverage for `app/` directory.

## Testability Tiers

### Tier 1 — Trivial (35 files)

Pure data classes, interfaces, attributes, enums. No dependencies, no mocking needed.

#### Consumer Classes (5 files)
| File | What to test |
|------|-------------|
| `Core/Contracts/Completion/CompletionConsumer.php` | Invoke with items, verify `getReturn()` collects them |
| `Core/Contracts/Declaration/DeclarationConsumer.php` | Same pattern — invoke and verify array |
| `Core/Contracts/Documentation/DocumentationConsumer.php` | Same pattern |
| `Core/Contracts/References/ReferenceConsumer.php` | Same pattern |
| `Core/Contracts/Signature/SignatureConsumer.php` | Same pattern |

#### Context DTOs (4 files)
| File | What to test |
|------|-------------|
| `Core/Contracts/Completion/CompletionContext.php` | Construct with mocked deps, verify getters |
| `Core/Contracts/Declaration/DeclarationContext.php` | Construct, verify immutable properties |
| `Core/Contracts/Documentation/DocumentationContext.php` | Construct, verify immutable properties |
| `Core/Contracts/References/ReferenceContext.php` | Construct, verify immutable properties |
| `Core/Contracts/Signature/SignatureContext.php` | Construct, verify immutable properties |

#### Interfaces (8 files) — no tests needed
| File | Notes |
|------|-------|
| `Core/Contracts/Completion/CompletionContributor.php` | Interface only |
| `Core/Contracts/Declaration/DeclarationContributor.php` | Interface only |
| `Core/Contracts/Documentation/DocumentationContributor.php` | Interface only |
| `Core/Contracts/References/ReferenceContributor.php` | Interface only |
| `Core/Contracts/Signature/SignatureContributor.php` | Interface only |
| `Core/Contracts/Indexing/IndexerInterface.php` | Interface only |
| `Core/Contracts/PrefixMatcher/PrefixMatcher.php` | Interface only |
| `Module/Document/DocumentLoaderInterface.php` | Interface only |
| `Module/Document/DocumentIdentifierFactoryInterface.php` | Interface only |
| `Module/Indexing/Storage/StorageInterface.php` | Interface only |
| `Module/Indexing/Storage/SerializerInterface.php` | Interface only |

#### Attributes (6 files) — no tests needed
| File | Notes |
|------|-------|
| `Core/Contracts/Completion/AsCompletionContributor.php` | Marker attribute |
| `Core/Contracts/Declaration/AsDeclarationContributor.php` | Marker attribute |
| `Core/Contracts/Documentation/AsDocumentationContributor.php` | Marker attribute |
| `Core/Contracts/References/AsReferenceContributor.php` | Marker attribute |
| `Core/Contracts/Signature/AsSignatureContributor.php` | Marker attribute |
| `Core/Contracts/Indexing/AsIndexer.php` | Marker attribute |

#### Pure Logic Classes (12 files)
| File | What to test |
|------|-------------|
| `Core/Contracts/PrefixMatcher/StrContainsMatcher.php` | `matches('haystack', 'hay')` → true, `matches('foo', 'bar')` → false |
| `Module/Completion/KeywordCategory.php` | Enum — verify cases exist |
| `Module/Completion/KeywordDefinitions.php` | `getKeywords()` returns expected array; `getCategory()` returns correct category for each keyword |
| `Module/Indexing/Storage/Entry.php` | Construct, verify readonly properties |
| `Module/Indexing/Storage/JsonSerializer.php` | `serialize()` → JSON string; `deserialize()` → Entry objects |
| `Module/PsiFile/SourceFileRoot.php` | Construct, verify properties |
| `Module/PsiFile/FifoCache.php` | Set/get, eviction at max size, remove, clear |
| `Module/Workspace/ProjectManager.php` | `getProject()`/`setProject()` round-trip |
| `Module/Notification/Result.php` | Static factory methods, verify values |
| `Module/Notification/ActiveConnectionProvider.php` | `get()`/`set()` round-trip, null when unset |
| `Module/Notification/ProgressNotifier.php` | create/begin/report/end methods, percentage clamping (0-100) |
| `Module/Indexing/IndexerFileCollector.php` | File collection with ignored directories, nested traversal |

---

### Tier 2 — Easy (25 files)

Interface dependencies that are simple to mock. Straightforward logic.

#### Completion Contributors (3 files)
| File | Dependencies to mock | What to test |
|------|---------------------|-------------|
| `Module/Completion/KeywordsCompletionContributor.php` | `CompletionContext` | Yields keyword items matching prefix |
| `Module/Completion/SuperglobalsCompletionContributor.php` | `CompletionContext` | Yields `$_GET`, `$_POST`, etc. matching prefix |
| `Module/Completion/ShortcutCompletionContributor.php` | `CompletionContext` | Yields shortcut completions based on node type |

#### Declaration Contributors (3 files)
| File | Dependencies to mock | What to test |
|------|---------------------|-------------|
| `Module/Declaration/ClassDeclarationContributor.php` | `IndexLookup`, `InMemoryPsiFileManager` | Finds class declaration location |
| `Module/Declaration/ClassMethodDeclarationContributor.php` | `IndexLookup`, `InMemoryPsiFileManager` | Finds method declaration location |
| `Module/Declaration/FunctionDeclarationContributor.php` | `IndexLookup`, `InMemoryPsiFileManager`, `DocumentIdentifierFactoryInterface` | Finds function declaration location |

#### Documentation & Signature Contributors (2 files)
| File | Dependencies to mock | What to test |
|------|---------------------|-------------|
| `Module/Documentation/NodesTraceDocumentationContributor.php` | `InMemoryPsiFileManager` | Builds node trace documentation |
| `Module/Signature/FunctionSignatureContributor.php` | `InMemoryPsiFileManager`, `IndexLookup` | Extracts function parameter signatures |

#### Document Module (1 file)
| File | Dependencies to mock | What to test |
|------|---------------------|-------------|
| `Module/Document/InMemoryDocumentIdentifierFactory.php` | (self-contained, uses FifoCache) | Creates and caches TextDocumentIdentifier |

#### Listeners (4 files)
| File | Dependencies to mock | What to test |
|------|---------------------|-------------|
| `Listener/MessageListener.php` | `LoggerInterface` | Verify logger called with message |
| `Listener/ServerListener.php` | `LoggerInterface` | Verify logger called on server events |
| `Listener/ActiveConnectionListener.php` | `ActiveConnectionProvider`, `MessageReceived` event | Verify connection is stored in provider |
| `Listener/ExceptionNotificationListener.php` | `ServerNotificationSender`, `FailureResponseSent` event | Verify non-ignored error codes trigger notifications |

#### Controllers — Simple (9 files)
| File | Dependencies to mock | What to test |
|------|---------------------|-------------|
| `Controller/SetTraceController.php` | `LoggerInterface` | Logs trace value |
| `Controller/InitializedController.php` | `LoggerInterface`, `ServerNotificationSender` | Logs and sends notification |
| `Controller/TextDocument/HoverController.php` | `iterable<DocumentationContributor>` | Iterates contributors, returns hover |
| `Controller/TextDocument/ReferencesController.php` | `iterable<ReferenceContributor>` | Iterates contributors, returns locations |
| `Controller/TextDocument/SignatureHelpController.php` | `iterable<SignatureContributor>` | Iterates contributors, returns signatures |
| `Controller/TextDocument/PrepareRenameController.php` | `InMemoryPsiFileManager` | Finds node range at position |
| `Controller/TextDocument/PublishDiagnosticsController.php` | `ServerNotificationSender` | Delegates to notification sender |
| `Controller/TextDocument/DocumentSymbolController.php` | `InMemoryPsiFileManager` | Traverses AST, builds symbol list |
| `Controller/TextDocument/DeclarationController.php` | `iterable<DeclarationContributor>` | Iterates contributors, returns locations |

---

### Tier 3 — Medium (17 files)

Multiple dependencies or AST work. Testable with careful mock setup.

#### Completion with Index (2 files)
| File | Dependencies to mock | What to test |
|------|---------------------|-------------|
| `Module/Completion/ClassesCompletionContributor.php` | `IndexLookup` | Queries index, filters by prefix matcher |
| `Module/Completion/FunctionCompletionContributor.php` | `IndexLookup` | Same pattern as classes |

#### Indexing Infrastructure (2 files)
| File | Dependencies to mock | What to test |
|------|---------------------|-------------|
| `Module/Indexing/IndexLookup.php` | `StorageInterface` | Wraps storage queries |
| `Module/Indexing/Storage/InMemoryStorage.php` | (none — internal state) | CRUD operations on in-memory store |

#### Indexers (6 files)
| File | Dependencies to mock | What to test |
|------|---------------------|-------------|
| `Module/Indexing/Indexer/AbstractPhpIndexer.php` | `InMemoryPsiFileManager` | Template method — test via subclasses |
| `Module/Indexing/Indexer/ClassIndexer.php` | (inherited) | Finds Class_ nodes in AST |
| `Module/Indexing/Indexer/InterfaceIndexer.php` | (inherited) | Finds Interface_ nodes |
| `Module/Indexing/Indexer/TraitIndexer.php` | (inherited) | Finds Trait_ nodes |
| `Module/Indexing/Indexer/FunctionIndexer.php` | (inherited) | Finds Function_ nodes |
| `Module/Indexing/Indexer/ClassMethodIndexer.php` | (inherited) | Finds ClassMethod nodes |

#### PsiFile / AST (3 files)
| File | Dependencies to mock | What to test |
|------|---------------------|-------------|
| `Module/PsiFile/PHPPsiFile.php` | (none — wraps AST) | `findNodeAtPosition()`, `findNodesAtPosition()` |
| `Module/PsiFile/PHPPsiFileParser.php` | (none — uses php-parser) | Parse valid/invalid PHP, verify AST output |
| `Module/PsiFile/Tree.php` | (none — pure static) | `childrenOfType()`, `parentOfType()`, etc. |

#### Remaining (4 files)
| File | Dependencies to mock | What to test |
|------|---------------------|-------------|
| `Module/Documentation/DocblockDocumentationContributor.php` | `PHPStanAnalyzer`, `IndexLookup` | Extracts docblock documentation |
| `Controller/TextDocument/DiagnosticController.php` | `PHPPsiFileParser`, `DocumentLoaderInterface` | Converts parse errors to diagnostics |
| `Controller/TextDocument/RenameController.php` | `iterable`, `InMemoryPsiFileManager` | Incomplete impl — test what exists |
| `Infrastructure/Symfony/LSPCompilerPass.php` | `ContainerBuilder` | Empty impl — verify no-op |

---

### Tier 4 — Hard (6 files)

Async I/O, deep framework coupling, external tool integration. Consider integration tests.

| File | Difficulty reason | Strategy |
|------|------------------|----------|
| `Module/Indexing/Indexer.php` | async/await, filesystem walking, multiple deps | Integration test or mock filesystem + async |
| `Module/Document/LocalFileDocumentLoader.php` | React async file I/O | Mock AdapterInterface, test promise resolution |
| `Module/PsiFile/InMemoryPsiFileManager.php` | 5 deps, dispatcher coupling, cache versioning | Mock all deps, test cache logic separately |
| `Controller/PHPStanAnalyzer.php` | Creates PHPStan container, reflection | Integration test with real PHPStan container |
| `Controller/TextDocument/CompletionController.php` | React async/promises with timeouts | Mock contributors, test without async layer |
| `Controller/InitializeController.php` | Depends on async Indexer + notifications | Mock Indexer, test capability response |

---

## Execution Order

1. **Tier 1** — Quick wins. ~12 test classes for the non-interface/attribute files.
2. **Tier 2** — Bulk coverage. ~14 test classes with simple mocks.
3. **Tier 3** — Deeper tests. ~13 test classes with AST fixtures and index mocks.
4. **Tier 4** — Final push. ~6 test classes, possibly integration-style.

## Test File Conventions

- Namespace: `App\Tests\Unit\{mirrors app/ structure}`
- Location: `tests/Unit/{mirrors app/ structure}`
- Naming: `{ClassName}Test.php`
- Base class: `App\Tests\TestCase`
- Attributes: `#[Group('unit')]`, `#[TestDox('...')]`

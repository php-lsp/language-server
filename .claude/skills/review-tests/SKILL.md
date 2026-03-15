---
name: review-tests
description: Write and review tests — unit, functional, and E2E. Enforces test doubles over inline mocks, consistent structure, and coverage rules. Use when writing new tests or fixing existing ones.
disable-model-invocation: false
allowed-tools: Read, Glob, Grep, Edit, Write, Bash
---

# Skill: Tests

## Pipeline Position

Run when approaching the test-writing phase or when fixing broken tests.
Runs before static analysis and formatting in the pipeline.

## Goal

Write and maintain tests following uniform rules. Prefer explicit test doubles
over inline mocks. Keep tests readable, fast, and deterministic.

## Test Suites

| Suite | Directory | Group | Command | Purpose |
|-------|-----------|-------|---------|---------|
| Unit | `tests/Unit/` | `unit` | `composer test:unit` | Isolated class testing |
| Functional | `tests/Functional/` | `functional` | `composer test:functional` | Multi-class integration |
| E2E | `tests/Playground/` | `e2e` | `composer test:e2e` | Real server over TCP |

Config: `phpunit.xml`

## Directory Mirroring

Test directory mirrors `app/`:

```
app/Module/Completion/ClassesCompletionContributor.php
tests/Unit/Module/Completion/ClassesCompletionContributorTest.php

app/Controller/TextDocument/CompletionController.php
tests/Unit/Controller/TextDocument/CompletionControllerTest.php
```

## Test File Structure

Every test file follows this template:

```php
<?php

declare(strict_types=1);

namespace App\Tests\Unit\Module\{Module};

use App\Tests\TestCase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\TestDox;

#[Group('unit')]  // or 'functional', 'e2e'
final class {ClassName}Test extends TestCase
{
    #[TestDox('description of behavior')]
    public function testBehaviorDescription(): void
    {
        // Arrange
        // Act
        // Assert
    }
}
```

Rules:
- Extend `App\Tests\TestCase` (for unit/functional) or
  `App\Tests\Playground\PlaygroundTestCase` (for E2E).
- Every test method has `#[TestDox('...')]` describing behavior in plain
  English, lowercase.
- Every test class has `#[Group('unit')]` (or `functional`, `e2e`).
- Class is `final`.
- Method names: `testDescriptiveBehavior` (camelCase, starts with `test`).

## Test Doubles — No Inline Mocks

**Rule: never use `$this->createMock()` or `$this->getMockBuilder()` for
project classes.** Use dedicated test doubles instead.

### Existing Test Support Classes

Located in `tests/Support/`:

| Class | Purpose | Usage |
|-------|---------|-------|
| `MockHelper` | Create mock objects outside PHPUnit context | `MockHelper::mock(ClassName::class)` |
| `ProtocolFactory` | Create LSP protocol objects | `ProtocolFactory::position(0, 5)`, `ProtocolFactory::textDocumentIdentifier()`, `ProtocolFactory::location()`, `ProtocolFactory::completionItem()`, `ProtocolFactory::signatureInformation()`, `ProtocolFactory::range()` |
| `PsiFileFactory` | Parse PHP code into PsiFile | `PsiFileFactory::fromCode('<?php ...')`, `PsiFileFactory::document('<?php ...')` |
| `IndexTestHelper` | Create IndexLookup with test data | `IndexTestHelper::createLookup(['key' => ['uri' => [data]]])` |
| `CompletionTestHelper` | Run completion contributor and collect results | `CompletionTestHelper::contribute($contributor, $psiFile, $position)` |
| `DeclarationTestHelper` | Run declaration contributor and collect results | Similar to CompletionTestHelper |
| `DefinitionTestHelper` | Run definition contributor and collect results | Similar to CompletionTestHelper |
| `IndexerTestHelper` | Run indexer on code and collect entries | For testing indexers |
| `TypeResolverTestHelper` | Test type resolution | For TypeSystem tests |
| `VirtualFileStub` | Stub for VirtualFileInterface | For indexer tests |

### When to Create New Test Doubles

Create a new test double in `tests/Support/` when:
- The same mock setup repeats in 3+ test files.
- A complex dependency needs realistic behavior (not just null returns).
- An anonymous class implementing an interface is used in 2+ places.

Use anonymous classes implementing interfaces for one-off contributors:

```php
$contributor = new class implements CompletionContributor {
    public function contribute(CompletionContext $context, CompletionConsumer $consumer): void
    {
        ($consumer)(new CompletionItem(label: 'test'));
    }
};
```

### Allowed Mock Usage

`$this->createMock()` is acceptable ONLY for:
- Third-party library interfaces (`LoggerInterface`, `EventDispatcherInterface`)
- Simple spy assertions (`$logger->expects($this->once())->method('error')`)

For project classes — always use `MockHelper::mock()` or dedicated helpers.

## What to Test

### Contributors (unit)

Test each contributor in isolation:

1. **Happy path** — correct input produces expected output.
2. **No match** — input that doesn't match returns empty.
3. **Edge cases** — null nodes, empty files, boundary positions.
4. **Filtering** — prefix matching works correctly.

Pattern:
```php
$lookup = IndexTestHelper::createLookup([...]);
$contributor = new FooContributor($lookup);
$psiFile = PsiFileFactory::fromCode('<?php ...');
$results = CompletionTestHelper::contribute($contributor, $psiFile, $position);
$this->assertCount(1, $results);
```

### Controllers (unit)

Test controller orchestration:

1. **Empty contributors** — returns empty result.
2. **Collects from contributors** — aggregates results correctly.
3. **Handles exceptions** — contributor failure doesn't crash, logs error.

### Index Data Classes (unit)

Test constructors, readonly properties, serialization.

### Storage (unit)

Test CRUD operations, secondary indexes, edge cases.

### E2E (playground)

Test real LSP interactions via `PlaygroundTestCase`:

1. Open files, send LSP requests, verify responses.
2. Use `self::openPlaygroundFile()`, `self::completion()`, etc.
3. Cache responses in static properties to avoid repeated requests.
4. Test against files in `playground/` workspace.

## Coverage Rules

- New features: >= 80% line coverage.
- Check: `php -dpcov.enabled=1 vendor/bin/phpunit --coverage-text`
- Every public method should have at least one test.
- Prefer testing behavior over implementation details.

## Test Quality Checks

When reviewing tests, verify:

1. **No test logic** — no `if/else` or loops in test methods. Each test
   is a straight line: arrange, act, assert.
2. **One assertion group per test** — test one behavior, use multiple
   related assertions. Don't test unrelated things together.
3. **Descriptive TestDox** — `#[TestDox('returns empty when node is null')]`
   not `#[TestDox('test1')]`.
4. **No shared mutable state** — tests must not depend on execution order.
5. **Fast** — unit tests must not do I/O, network, or sleep.
6. **Deterministic** — no random data, no time-dependent assertions.

## Output

When writing tests:
1. Create test file mirroring `app/` structure.
2. Use existing test helpers from `tests/Support/`.
3. Create new helpers in `tests/Support/` if patterns repeat.
4. Verify tests pass: `composer test:unit`.

When reviewing tests:
1. Flag inline mocks of project classes — replace with helpers.
2. Flag missing `#[TestDox]` or `#[Group]` attributes.
3. Flag test logic (if/else/loops in test methods).
4. Flag coverage gaps for new features.

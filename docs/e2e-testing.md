# E2E Testing Guide

End-to-end (E2E) tests verify the LSP server's behavior by running a **real server process**, connecting to it over TCP, and sending actual JSON-RPC/LSP messages. This catches integration issues that unit tests miss.

## Architecture Overview

```
┌─────────────────────────────┐
│       PHPUnit Test          │
│  (tests/Playground/*.php)   │
│         │                   │
│    PlaygroundTestCase       │
│     ┌────┴─────┐           │
│     │          │            │
│  LspClient  ServerProcess   │
│     │          │            │
└─────┼──────────┼────────────┘
      │ TCP      │ proc_open
      ▼          ▼
   ┌────────────────────┐
   │   LSP Server       │
   │   (bin/lsp serve)  │
   │   on random port   │
   └────────────────────┘
         │
         ▼
   ┌────────────────────┐
   │   playground/      │
   │   (workspace)      │
   └────────────────────┘
```

### Components

| Component | Location | Purpose |
|-----------|----------|---------|
| `PlaygroundTestCase` | `tests/Playground/PlaygroundTestCase.php` | Base class — manages server lifecycle, client connection, initialization |
| `LspClient` | `tests/Playground/Support/LspClient.php` | TCP client — sends/receives LSP messages with Content-Length framing |
| `ServerProcess` | `tests/Playground/Support/ServerProcess.php` | Process manager — starts `bin/lsp`, finds free port, waits for readiness |
| `playground/` | `playground/` | Sample PHP workspace with classes, interfaces, enums for testing |

## Running E2E Tests

```bash
# Run all E2E tests
composer test:e2e

# Or directly via PHPUnit
php vendor/bin/phpunit --testsuite playground --testdox

# Run a specific test class
php vendor/bin/phpunit tests/Playground/CompletionTest.php --testdox

# Run with verbose output for debugging
php vendor/bin/phpunit --testsuite playground --testdox -v
```

## Writing a New E2E Test

### 1. Create a test class

Create a new file in `tests/Playground/` extending `PlaygroundTestCase`:

```php
<?php

declare(strict_types=1);

namespace App\Tests\Playground;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\TestDox;

#[Group('e2e')]
#[TestDox('LSP My Feature')]
final class MyFeatureTest extends PlaygroundTestCase
{
    // Increase if your test needs more time for indexing
    protected static float $indexingWaitTime = 3.0;

    private static bool $filesOpened = false;

    protected function setUp(): void
    {
        parent::setUp();

        // Open files once per class (optimization)
        if (!self::$filesOpened) {
            self::openPlaygroundFile('src/Greeter.php');
            self::$filesOpened = true;
        }
    }

    #[TestDox('My feature works correctly')]
    public function testMyFeature(): void
    {
        $response = self::$client->request('textDocument/myMethod', [
            'textDocument' => ['uri' => self::playgroundFileUri('src/Greeter.php')],
            'position' => ['line' => 10, 'character' => 5],
        ]);

        self::assertResponseOk($response);
        // Assert on $response['result']
    }
}
```

### 2. Add playground source files (if needed)

If your test needs specific PHP code patterns, add files to `playground/src/`:

```php
// playground/src/MyTestClass.php
<?php

namespace Playground;

class MyTestClass
{
    public function myMethod(): void
    {
        // Code that exercises the LSP feature you're testing
    }
}
```

Update `playground/composer.json` if you need new dependencies.

### 3. Server lifecycle

The base class handles everything automatically:

1. **`setUpBeforeClass()`** — Starts server, connects client, sends `initialize`/`initialized`
2. **Each test** — Uses `self::$client` to send requests
3. **`tearDownAfterClass()`** — Sends `shutdown`/`exit`, stops server process

Each test **class** gets its own server instance. Tests within a class share the server.

### 4. Helper methods

The `PlaygroundTestCase` provides convenience methods:

```php
// Open a file in the LSP editor
self::openPlaygroundFile('src/Greeter.php');

// Get file URI
$uri = self::playgroundFileUri('src/Greeter.php');

// LSP feature shortcuts
$response = self::completion('src/Greeter.php', $line, $character);
$response = self::hover('src/Greeter.php', $line, $character);
$response = self::declaration('src/Greeter.php', $line, $character);
$response = self::references('src/Greeter.php', $line, $character);
$response = self::signatureHelp('src/Greeter.php', $line, $character);

// Assert helpers
self::assertResponseOk($response);
self::assertResponseError($response);
$labels = self::extractCompletionLabels($response);
```

### 5. Raw client access

For advanced scenarios, use `self::$client` directly:

```php
// Send any request
$response = self::$client->request('textDocument/formatting', [
    'textDocument' => ['uri' => $uri],
    'options' => ['tabSize' => 4, 'insertSpaces' => true],
]);

// Send a notification
self::$client->notify('textDocument/didSave', [
    'textDocument' => ['uri' => $uri],
]);

// Read server notifications
$notifications = self::$client->drainNotifications(1.0);
foreach ($notifications as $notification) {
    echo $notification['method'] . ': ' . json_encode($notification['params']);
}
```

## Adding Playground Dependencies

Edit `playground/composer.json` to add packages. These packages become part of the workspace that the LSP server indexes:

```json
{
    "require": {
        "psr/log": "^3.0",
        "some/package": "^1.0"
    }
}
```

Then run `composer install` in the `playground/` directory. The LSP server will index both your source files and installed dependencies.

## Code Coverage

E2E tests can collect code coverage from the server process.

### Prerequisites

Install one of these PHP extensions on the system running the server:
- **PCOV** (recommended, faster)
- **Xdebug** (mode=coverage)

### Running with Coverage

```bash
# Set the coverage output directory
E2E_COVERAGE_DIR=var/coverage composer test:e2e

# Or directly
E2E_COVERAGE_DIR=var/coverage php vendor/bin/phpunit --testsuite playground
```

### Merging Coverage Reports

After tests complete, merge the `.cov` files into a report:

```bash
# Generate Clover XML (for CI)
php tests/Playground/Support/merge-coverage.php var/coverage \
    --clover=var/coverage/clover.xml

# Generate HTML report
php tests/Playground/Support/merge-coverage.php var/coverage \
    --html=var/coverage/html

# Both
php tests/Playground/Support/merge-coverage.php var/coverage \
    --clover=var/coverage/clover.xml \
    --html=var/coverage/html
```

### How Coverage Works

1. `ServerProcess` starts `bin/lsp` with PCOV/Xdebug enabled
2. The server process runs with coverage tracking active
3. On server shutdown, a shutdown handler writes coverage data to a `.cov` file
4. `merge-coverage.php` combines all `.cov` files from multiple test runs
5. Reports are generated in Clover XML and/or HTML format

## Debugging

### Server output

If a test fails, check the server's stdout/stderr:

```php
public function testSomething(): void
{
    echo self::$server->getStdout();
    echo self::$server->getStderr();
    // ...
}
```

### Connection issues

- The server starts on a random free port (collision-free)
- Startup timeout is 15 seconds — increase `ServerProcess::STARTUP_TIMEOUT` if needed
- Check that `bin/lsp` is executable and PHP 8.4+ is available

### Notifications

The server may send notifications (log messages, diagnostics). Drain them to prevent buffer overflow:

```php
$notifications = self::$client->drainNotifications(1.0);
```

## Best Practices

1. **Group tests by feature** — One test class per LSP feature (completion, hover, etc.)
2. **Use `#[Group('e2e')]`** — So E2E tests can be filtered separately
3. **Open files once** — Use a static `$filesOpened` flag to avoid redundant didOpen notifications
4. **Set appropriate timeouts** — Increase `$indexingWaitTime` for tests that need full indexing
5. **Test the protocol** — Assert response structure, not just that it "works"
6. **Add playground sources** — Create specific PHP files that exercise the feature you're testing
7. **Keep playground code simple** — Focus on testing LSP, not complex PHP logic

<?php

declare(strict_types=1);

namespace App\Tests\Playground;

use App\Core\UriHelper;
use App\Tests\Playground\Support\LspClient;
use App\Tests\Playground\Support\ServerProcess;
use PHPUnit\Framework\TestCase;

/**
 * Base test case for E2E (playground) tests.
 *
 * Manages the LSP server lifecycle: starts the server before the first test,
 * initializes it with the playground workspace, and shuts it down after
 * all tests in the class have completed.
 *
 * Each test class gets its own server instance and client connection, ensuring
 * test isolation at the class level while sharing the server within a class
 * for performance.
 *
 * ## How It Works
 *
 * 1. `setUpBeforeClass()` starts the server process and connects the client
 * 2. The `initialize` handshake is performed once, pointing at `playground/` as workspace
 * 3. A configurable wait period allows indexing to complete
 * 4. Each test method uses `self::$client` to send requests
 * 5. `tearDownAfterClass()` shuts down the server
 *
 * ## Example
 *
 * ```php
 * class MyFeatureTest extends PlaygroundTestCase
 * {
 *     public function testCompletion(): void
 *     {
 *         self::openPlaygroundFile('src/Greeter.php');
 *
 *         $response = self::$client->request('textDocument/completion', [
 *             'textDocument' => ['uri' => self::playgroundFileUri('src/Greeter.php')],
 *             'position' => ['line' => 10, 'character' => 15],
 *         ]);
 *
 *         self::assertArrayHasKey('result', $response);
 *     }
 * }
 * ```
 */
abstract class PlaygroundTestCase extends TestCase
{
    protected static ?ServerProcess $server = null;
    protected static ?LspClient $client = null;

    /** @var array<string, mixed>|null Cached initialize result */
    protected static ?array $initializeResult = null;

    /**
     * How long to wait after initialization for indexing to complete (seconds).
     * Override in subclasses if your tests require longer indexing time.
     */
    protected static float $indexingWaitTime = 2.0;

    /**
     * Timeout for individual LSP requests (seconds).
     */
    protected static float $requestTimeout = 30.0;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        $projectRoot = self::getProjectRoot();
        $coverageDir = self::getCoverageDir();

        // Start the server
        self::$server = new ServerProcess($projectRoot, $coverageDir);
        self::$server->start();

        // Connect the client
        self::$client = new LspClient();
        self::$client->connect(
            self::$server->getHost(),
            self::$server->getPort(),
        );

        // Perform LSP initialize handshake
        $playgroundPath = $projectRoot . '/playground';
        $rootUri = UriHelper::toFileUri($playgroundPath);

        self::$initializeResult = self::$client->initialize($rootUri, [
            'textDocument' => [
                'completion' => [
                    'completionItem' => [
                        'snippetSupport' => true,
                    ],
                ],
                'hover' => [
                    'contentFormat' => ['markdown', 'plaintext'],
                ],
            ],
            'workspace' => [
                'workspaceFolders' => true,
            ],
        ]);

        // Wait for indexing to complete
        if (static::$indexingWaitTime > 0) {
            \usleep((int) (static::$indexingWaitTime * 1_000_000));
            // Drain any notifications that came during indexing
            self::$client->drainNotifications(0.5);
        }
    }

    public static function tearDownAfterClass(): void
    {
        // Graceful shutdown
        if (self::$client !== null) {
            self::$client->shutdown();
            self::$client->disconnect();
            self::$client = null;
        }

        if (self::$server !== null) {
            self::$server->stop();
            self::$server = null;
        }

        self::$initializeResult = null;

        parent::tearDownAfterClass();
    }

    /**
     * Open a file from the playground workspace in the LSP editor.
     *
     * @param string $relativePath Path relative to playground/ (e.g. "src/Greeter.php")
     */
    protected static function openPlaygroundFile(string $relativePath): void
    {
        $fullPath = self::getProjectRoot() . '/playground/' . $relativePath;
        $uri = self::playgroundFileUri($relativePath);
        $content = \file_get_contents($fullPath);

        if ($content === false) {
            throw new \RuntimeException("Failed to read playground file: {$fullPath}");
        }

        self::$client->openDocument($uri, $content);

        // Small delay to allow the server to process the didOpen notification
        \usleep(500_000);
    }

    /**
     * Get the file URI for a playground file.
     *
     * @param string $relativePath Path relative to playground/ (e.g. "src/Greeter.php")
     * @return string File URI
     */
    protected static function playgroundFileUri(string $relativePath): string
    {
        return UriHelper::toFileUri(self::getProjectRoot() . '/playground/' . $relativePath);
    }

    /**
     * Send a textDocument/completion request.
     *
     * @param string $relativePath File path relative to playground/
     * @param int $line Zero-based line number
     * @param int $character Zero-based character offset
     * @return array<string, mixed> The response
     */
    protected static function completion(string $relativePath, int $line, int $character): array
    {
        return self::$client->request('textDocument/completion', [
            'textDocument' => ['uri' => self::playgroundFileUri($relativePath)],
            'position' => ['line' => $line, 'character' => $character],
        ], static::$requestTimeout);
    }

    /**
     * Send a textDocument/definition request.
     *
     * @param string $relativePath File path relative to playground/
     * @param int $line Zero-based line number
     * @param int $character Zero-based character offset
     * @return array<string, mixed> The response
     */
    protected static function definition(string $relativePath, int $line, int $character): array
    {
        return self::$client->request('textDocument/definition', [
            'textDocument' => ['uri' => self::playgroundFileUri($relativePath)],
            'position' => ['line' => $line, 'character' => $character],
        ], static::$requestTimeout);
    }

    /**
     * Send a textDocument/typeDefinition request.
     *
     * @param string $relativePath File path relative to playground/
     * @param int $line Zero-based line number
     * @param int $character Zero-based character offset
     * @return array<string, mixed> The response
     */
    protected static function typeDefinition(string $relativePath, int $line, int $character): array
    {
        return self::$client->request('textDocument/typeDefinition', [
            'textDocument' => ['uri' => self::playgroundFileUri($relativePath)],
            'position' => ['line' => $line, 'character' => $character],
        ], static::$requestTimeout);
    }

    /**
     * Send a textDocument/hover request.
     *
     * @param string $relativePath File path relative to playground/
     * @param int $line Zero-based line number
     * @param int $character Zero-based character offset
     * @return array<string, mixed> The response
     */
    protected static function hover(string $relativePath, int $line, int $character): array
    {
        return self::$client->request('textDocument/hover', [
            'textDocument' => ['uri' => self::playgroundFileUri($relativePath)],
            'position' => ['line' => $line, 'character' => $character],
        ], static::$requestTimeout);
    }

    /**
     * Send a textDocument/declaration request.
     *
     * @param string $relativePath File path relative to playground/
     * @param int $line Zero-based line number
     * @param int $character Zero-based character offset
     * @return array<string, mixed> The response
     */
    protected static function declaration(string $relativePath, int $line, int $character): array
    {
        return self::$client->request('textDocument/declaration', [
            'textDocument' => ['uri' => self::playgroundFileUri($relativePath)],
            'position' => ['line' => $line, 'character' => $character],
        ], static::$requestTimeout);
    }

    /**
     * Send a textDocument/references request.
     *
     * @param string $relativePath File path relative to playground/
     * @param int $line Zero-based line number
     * @param int $character Zero-based character offset
     * @return array<string, mixed> The response
     */
    protected static function references(string $relativePath, int $line, int $character): array
    {
        return self::$client->request('textDocument/references', [
            'textDocument' => ['uri' => self::playgroundFileUri($relativePath)],
            'position' => ['line' => $line, 'character' => $character],
            'context' => ['includeDeclaration' => true],
        ], static::$requestTimeout);
    }

    /**
     * Send a textDocument/signatureHelp request.
     *
     * @param string $relativePath File path relative to playground/
     * @param int $line Zero-based line number
     * @param int $character Zero-based character offset
     * @return array<string, mixed> The response
     */
    protected static function signatureHelp(string $relativePath, int $line, int $character): array
    {
        return self::$client->request('textDocument/signatureHelp', [
            'textDocument' => ['uri' => self::playgroundFileUri($relativePath)],
            'position' => ['line' => $line, 'character' => $character],
        ], static::$requestTimeout);
    }

    /**
     * Send a textDocument/foldingRange request.
     *
     * @param string $relativePath File path relative to playground/
     * @return array<string, mixed> The response
     */
    protected static function foldingRange(string $relativePath): array
    {
        return self::$client->request('textDocument/foldingRange', [
            'textDocument' => ['uri' => self::playgroundFileUri($relativePath)],
        ], static::$requestTimeout);
    }

    /**
     * Assert that the response JSON matches the expected structure.
     *
     * Compares the full message structure. The `id` and `jsonrpc` fields are
     * added automatically. Use `'@any'` as a wildcard value for fields that
     * vary between runs (e.g. dynamic URIs).
     *
     * @param array<string, mixed> $expected Expected `result` or `error` payload (without id/jsonrpc wrapper)
     * @param array<string, mixed> $actual The full JSON-RPC response from the server
     */
    protected static function assertJsonEquals(array $expected, array $actual, string $message = ''): void
    {
        // Build the full expected message, preserving the actual id
        $fullExpected = [
            'id' => $actual['id'] ?? null,
            ...$expected,
            'jsonrpc' => '2.0',
        ];

        $normalized = self::normalizeForComparison($fullExpected, $actual);

        static::assertEquals(
            $normalized['expected'],
            $normalized['actual'],
            $message ?: 'JSON-RPC response does not match expected structure',
        );
    }

    /**
     * Assert that a response contains a result (not an error).
     *
     * @param array<string, mixed> $response
     */
    protected static function assertResponseOk(array $response, string $message = ''): void
    {
        static::assertArrayHasKey('result', $response, $message ?: 'Response should have a result');
        static::assertArrayNotHasKey('error', $response, $message ?: 'Response should not have an error');
    }

    /**
     * Assert that a response contains an error.
     *
     * @param array<string, mixed> $response
     */
    protected static function assertResponseError(array $response, string $message = ''): void
    {
        static::assertArrayHasKey('error', $response, $message ?: 'Response should have an error');
    }

    /**
     * Extract completion item labels from a completion response.
     *
     * @param array<string, mixed> $response
     * @return list<string>
     */
    protected static function extractCompletionLabels(array $response): array
    {
        $items = $response['result'] ?? [];

        // Handle both CompletionList and direct array
        if (isset($items['items'])) {
            $items = $items['items'];
        }

        return \array_map(
            static fn(array $item): string => $item['label'],
            $items,
        );
    }

    /**
     * Recursively normalize expected/actual for comparison, resolving `@any` wildcards.
     *
     * @return array{expected: mixed, actual: mixed}
     */
    private static function normalizeForComparison(mixed $expected, mixed $actual): array
    {
        if ($expected === '@any') {
            return ['expected' => $actual, 'actual' => $actual];
        }

        if (\is_array($expected) && \is_array($actual)) {
            $normalizedExpected = [];
            $normalizedActual = [];

            // Check if it's a sequential (list) array
            if (\array_is_list($expected) && \array_is_list($actual)) {
                foreach ($expected as $i => $expValue) {
                    if (!\array_key_exists($i, $actual)) {
                        $normalizedExpected[$i] = $expValue;
                        continue;
                    }
                    $pair = self::normalizeForComparison($expValue, $actual[$i]);
                    $normalizedExpected[$i] = $pair['expected'];
                    $normalizedActual[$i] = $pair['actual'];
                }
                // Include extra actual items
                for ($i = \count($expected); $i < \count($actual); $i++) {
                    $normalizedActual[$i] = $actual[$i];
                }
            } else {
                // Associative array — compare all keys from expected
                foreach ($expected as $key => $expValue) {
                    if (!\array_key_exists($key, $actual)) {
                        $normalizedExpected[$key] = $expValue;
                        continue;
                    }
                    $pair = self::normalizeForComparison($expValue, $actual[$key]);
                    $normalizedExpected[$key] = $pair['expected'];
                    $normalizedActual[$key] = $pair['actual'];
                }
                // Include extra actual keys
                foreach ($actual as $key => $actValue) {
                    if (!\array_key_exists($key, $expected)) {
                        $normalizedActual[$key] = $actValue;
                    }
                }
            }

            return ['expected' => $normalizedExpected, 'actual' => $normalizedActual];
        }

        return ['expected' => $expected, 'actual' => $actual];
    }

    protected static function getProjectRoot(): string
    {
        return \dirname(__DIR__, 2);
    }

    private static function getCoverageDir(): ?string
    {
        $dir = \getenv('E2E_COVERAGE_DIR');
        return $dir !== false ? $dir : null;
    }
}

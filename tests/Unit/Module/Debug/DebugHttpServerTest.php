<?php

declare(strict_types=1);

namespace App\Tests\Unit\Module\Debug;

use App\Module\Debug\DebugHtmlRenderer;
use App\Module\Debug\DebugHttpServer;
use App\Module\Indexing\Storage\InMemoryStorage;
use App\Tests\TestCase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\TestDox;
use React\Http\Message\Response;
use React\Http\Message\ServerRequest;

#[Group('unit')]
final class DebugHttpServerTest extends TestCase
{
    private DebugHttpServer $server;
    private InMemoryStorage $storage;

    protected function setUp(): void
    {
        $this->storage = new InMemoryStorage();
        $this->storage->write('php.classes.fqn', [
            'App\\Foo' => 'App\\Foo',
            'App\\Bar' => 'App\\Bar',
            'App\\Controller\\Home' => 'App\\Controller\\Home',
        ], 'file:///src/Foo.php');
        $this->storage->write('php.functions.fqn', [
            'App\\hello' => 'App\\hello',
        ], 'file:///src/functions.php');

        $this->server = new DebugHttpServer($this->storage, new DebugHtmlRenderer($this->storage));
    }

    private function request(string $method, string $uri): Response
    {
        $handleRequest = new \ReflectionMethod($this->server, 'handleRequest');

        $request = new ServerRequest($method, $uri);

        /** @var Response */
        return $handleRequest->invoke($this->server, $request);
    }

    /**
     * @param array<string, mixed> $body
     */
    private function postJson(string $uri, array $body): Response
    {
        $handleRequest = new \ReflectionMethod($this->server, 'handleRequest');

        $request = new ServerRequest('POST', $uri, ['Content-Type' => 'application/json'], json_encode($body));

        /** @var Response */
        return $handleRequest->invoke($this->server, $request);
    }

    // --- HTML views ---

    #[TestDox('GET / returns full HTML page with HTMX')]
    public function testRootReturnsHtml(): void
    {
        $response = $this->request('GET', '/');

        $this->assertSame(200, $response->getStatusCode());
        $this->assertStringContainsString('text/html', $response->getHeaderLine('Content-Type'));
        $body = (string) $response->getBody();
        $this->assertStringContainsString('<!DOCTYPE html>', $body);
        $this->assertStringContainsString('LSP Debug', $body);
        $this->assertStringContainsString('htmx.org', $body);
    }

    #[TestDox('GET /views/indexes returns index list fragment')]
    public function testViewIndexes(): void
    {
        $response = $this->request('GET', '/views/indexes');

        $this->assertSame(200, $response->getStatusCode());
        $this->assertStringContainsString('text/html', $response->getHeaderLine('Content-Type'));
        $body = (string) $response->getBody();
        $this->assertStringContainsString('php.classes.fqn', $body);
        $this->assertStringContainsString('php.functions.fqn', $body);
    }

    #[TestDox('GET /views/indexes/{name}/keys returns keys list fragment')]
    public function testViewKeys(): void
    {
        $response = $this->request('GET', '/views/indexes/php.classes.fqn/keys');

        $this->assertSame(200, $response->getStatusCode());
        $body = (string) $response->getBody();
        $this->assertStringContainsString('App\\Foo', $body);
        $this->assertStringContainsString('App\\Bar', $body);
    }

    #[TestDox('GET /views/indexes/{name}/keys with pattern filters results')]
    public function testViewKeysWithPattern(): void
    {
        $response = $this->request('GET', '/views/indexes/php.classes.fqn/keys?pattern=App%5CController%5C*');

        $body = (string) $response->getBody();
        $this->assertStringContainsString('App\\Controller\\Home', $body);
        $this->assertStringNotContainsString('App\\Foo', $body);
    }

    #[TestDox('GET /views/indexes/{name}/entries/{key} returns entry detail fragment')]
    public function testViewEntry(): void
    {
        $response = $this->request('GET', '/views/indexes/php.classes.fqn/entries/App%5CFoo');

        $this->assertSame(200, $response->getStatusCode());
        $body = (string) $response->getBody();
        $this->assertStringContainsString('App\\Foo', $body);
        $this->assertStringContainsString('file:///src/Foo.php', $body);
    }

    #[TestDox('GET /views/indexes/{name}/keys returns 404 for unknown index')]
    public function testViewKeysNotFound(): void
    {
        $response = $this->request('GET', '/views/indexes/nonexistent/keys');

        $this->assertSame(404, $response->getStatusCode());
    }

    #[TestDox('GET /views/indexes/ without name returns 400')]
    public function testViewIndexesNoName(): void
    {
        $response = $this->request('GET', '/views/indexes/');

        $this->assertSame(400, $response->getStatusCode());
    }

    // --- JSON API ---

    #[TestDox('GET /api/indexes returns all index stats')]
    public function testApiIndexes(): void
    {
        $response = $this->request('GET', '/api/indexes');
        $data = json_decode((string) $response->getBody(), true);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertStringContainsString('application/json', $response->getHeaderLine('Content-Type'));
        $this->assertArrayHasKey('php.classes.fqn', $data);
        $this->assertSame(3, $data['php.classes.fqn']['count']);
        $this->assertArrayHasKey('php.functions.fqn', $data);
    }

    #[TestDox('GET /api/indexes returns empty for fresh storage')]
    public function testApiIndexesEmpty(): void
    {
        $emptyStorage = new InMemoryStorage();
        $server = new DebugHttpServer($emptyStorage, new DebugHtmlRenderer($emptyStorage));
        $handleRequest = new \ReflectionMethod($server, 'handleRequest');

        $response = $handleRequest->invoke($server, new ServerRequest('GET', '/api/indexes'));
        $data = json_decode((string) $response->getBody(), true);

        $this->assertSame([], $data);
    }

    #[TestDox('GET /api/indexes/{name} returns index detail')]
    public function testApiIndexDetail(): void
    {
        $response = $this->request('GET', '/api/indexes/php.classes.fqn');
        $data = json_decode((string) $response->getBody(), true);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('php.classes.fqn', $data['key']);
        $this->assertSame(3, $data['count']);
    }

    #[TestDox('GET /api/indexes/{name} returns 404 for unknown index')]
    public function testApiIndexDetailNotFound(): void
    {
        $response = $this->request('GET', '/api/indexes/nonexistent');
        $data = json_decode((string) $response->getBody(), true);

        $this->assertSame(404, $response->getStatusCode());
        $this->assertArrayHasKey('error', $data);
        $this->assertArrayHasKey('available', $data);
    }

    #[TestDox('GET /api/indexes/{name}/keys returns all keys')]
    public function testApiKeys(): void
    {
        $response = $this->request('GET', '/api/indexes/php.classes.fqn/keys');
        $data = json_decode((string) $response->getBody(), true);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('php.classes.fqn', $data['index']);
        $this->assertSame(3, $data['total']);
        $this->assertCount(3, $data['keys']);
    }

    #[TestDox('GET /api/indexes/{name}/keys with pattern filters results')]
    public function testApiKeysWithPattern(): void
    {
        $response = $this->request('GET', '/api/indexes/php.classes.fqn/keys?pattern=App%5CController%5C*');
        $data = json_decode((string) $response->getBody(), true);

        $this->assertSame(1, $data['total']);
        $this->assertContains('App\\Controller\\Home', $data['keys']);
    }

    #[TestDox('GET /api/indexes/{name}/keys respects limit and offset')]
    public function testApiKeysWithPagination(): void
    {
        $response = $this->request('GET', '/api/indexes/php.classes.fqn/keys?limit=2&offset=1');
        $data = json_decode((string) $response->getBody(), true);

        $this->assertSame(3, $data['total']);
        $this->assertCount(2, $data['keys']);
        $this->assertSame(1, $data['offset']);
        $this->assertSame(2, $data['limit']);
    }

    #[TestDox('GET /api/indexes/{name}/search returns matching entries')]
    public function testApiSearch(): void
    {
        $response = $this->request('GET', '/api/indexes/php.classes.fqn/search?pattern=App%5CFoo');
        $data = json_decode((string) $response->getBody(), true);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame(1, $data['count']);
        $this->assertSame('App\\Foo', $data['results'][0]['key']);
    }

    #[TestDox('GET /api/indexes/{name}/search respects limit')]
    public function testApiSearchWithLimit(): void
    {
        $response = $this->request('GET', '/api/indexes/php.classes.fqn/search?pattern=App%5C*&limit=1');
        $data = json_decode((string) $response->getBody(), true);

        $this->assertSame(1, $data['count']);
    }

    #[TestDox('GET /api/indexes/{name}/search with no matches returns empty')]
    public function testApiSearchNoMatches(): void
    {
        $response = $this->request('GET', '/api/indexes/php.classes.fqn/search?pattern=Vendor%5C*');
        $data = json_decode((string) $response->getBody(), true);

        $this->assertSame(0, $data['count']);
        $this->assertSame([], $data['results']);
    }

    #[TestDox('GET /api/indexes/{name}/entries/{key} returns entry')]
    public function testApiEntry(): void
    {
        $response = $this->request('GET', '/api/indexes/php.classes.fqn/entries/App%5CFoo');
        $data = json_decode((string) $response->getBody(), true);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('App\\Foo', $data['key']);
        $this->assertSame('App\\Foo', $data['value']);
        $this->assertSame('file:///src/Foo.php', $data['uri']);
    }

    #[TestDox('GET /api/indexes/{name}/entries/{key} returns error for unknown key')]
    public function testApiEntryNotFound(): void
    {
        $response = $this->request('GET', '/api/indexes/php.classes.fqn/entries/App%5CNothing');
        $data = json_decode((string) $response->getBody(), true);

        $this->assertArrayHasKey('error', $data);
    }

    #[TestDox('GET /api/indexes/{name}/entries/{key} serializes objects')]
    public function testApiEntrySerializesObjects(): void
    {
        $this->storage->write('objs', ['k1' => (object) ['name' => 'test', 'count' => 5]], 'file:///a.php');

        $response = $this->request('GET', '/api/indexes/objs/entries/k1');
        $data = json_decode((string) $response->getBody(), true);

        $this->assertSame('stdClass', $data['value']['__class']);
        $this->assertSame('test', $data['value']['name']);
        $this->assertSame(5, $data['value']['count']);
    }

    // --- 404 ---

    #[TestDox('unknown path returns 404')]
    public function testUnknownPath(): void
    {
        $response = $this->request('GET', '/unknown');
        $this->assertSame(404, $response->getStatusCode());
    }

    #[TestDox('unknown API sub-action returns 404')]
    public function testUnknownAction(): void
    {
        $response = $this->request('GET', '/api/indexes/php.classes.fqn/foobar');
        $this->assertSame(404, $response->getStatusCode());
    }

    #[TestDox('unknown view sub-action returns 404')]
    public function testUnknownViewAction(): void
    {
        $response = $this->request('GET', '/views/indexes/php.classes.fqn/foobar');
        $this->assertSame(404, $response->getStatusCode());
    }

    // --- Global search view ---

    #[TestDox('GET /views/search returns global search results')]
    public function testViewGlobalSearch(): void
    {
        $response = $this->request('GET', '/views/search?pattern=Foo');

        $this->assertSame(200, $response->getStatusCode());
        $body = (string) $response->getBody();
        $this->assertStringContainsString('App\\Foo', $body);
        $this->assertStringContainsString('php.classes.fqn', $body);
    }

    #[TestDox('GET /views/search with empty pattern shows prompt')]
    public function testViewGlobalSearchEmpty(): void
    {
        $response = $this->request('GET', '/views/search');

        $this->assertSame(200, $response->getStatusCode());
        $body = (string) $response->getBody();
        $this->assertStringContainsString('Enter a search query', $body);
    }

    // --- Export API ---

    #[TestDox('GET /api/export returns all index data as JSON')]
    public function testApiExport(): void
    {
        $response = $this->request('GET', '/api/export');
        $data = json_decode((string) $response->getBody(), true);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertStringContainsString('application/json', $response->getHeaderLine('Content-Type'));
        $this->assertArrayHasKey('php.classes.fqn', $data);
        $this->assertArrayHasKey('php.functions.fqn', $data);
        $this->assertCount(3, $data['php.classes.fqn']);
        $this->assertSame('App\\Foo', $data['php.classes.fqn'][0]['key']);
    }

    // --- Global search API ---

    #[TestDox('GET /api/search returns results across all indexes')]
    public function testApiGlobalSearch(): void
    {
        $response = $this->request('GET', '/api/search?pattern=Foo');
        $data = json_decode((string) $response->getBody(), true);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertGreaterThanOrEqual(1, $data['count']);
        $this->assertSame('App\\Foo', $data['results'][0]['key']);
        $this->assertSame('php.classes.fqn', $data['results'][0]['index']);
    }

    #[TestDox('GET /api/search auto-wraps pattern with wildcards')]
    public function testApiGlobalSearchAutoWildcards(): void
    {
        $response = $this->request('GET', '/api/search?pattern=Controller');
        $data = json_decode((string) $response->getBody(), true);

        $this->assertSame('*Controller*', $data['pattern']);
        $this->assertGreaterThanOrEqual(1, $data['count']);
    }

    // --- Stats include memory ---

    #[TestDox('GET /api/indexes includes memory usage in stats')]
    public function testApiIndexesIncludesMemory(): void
    {
        $response = $this->request('GET', '/api/indexes');
        $data = json_decode((string) $response->getBody(), true);

        $this->assertArrayHasKey('memory', $data['php.classes.fqn']);
        $this->assertGreaterThan(0, $data['php.classes.fqn']['memory']);
    }

    // --- Batch actions ---

    #[TestDox('POST /api/batch with clear action clears selected indexes')]
    public function testBatchClear(): void
    {
        $response = $this->postJson('/api/batch', ['action' => 'clear', 'indexes' => ['php.classes.fqn']]);
        $data = json_decode((string) $response->getBody(), true);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('clear', $data['action']);
        $this->assertSame(['php.classes.fqn'], $data['indexes']);
        $this->assertSame('ok', $data['status']);

        // Verify index was actually cleared
        $this->assertSame(0, $this->storage->count('php.classes.fqn'));
        // Other index should remain
        $this->assertSame(1, $this->storage->count('php.functions.fqn'));
    }

    #[TestDox('POST /api/batch with export action returns selected index data')]
    public function testBatchExport(): void
    {
        $response = $this->postJson('/api/batch', ['action' => 'export', 'indexes' => ['php.functions.fqn']]);
        $data = json_decode((string) $response->getBody(), true);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('export', $data['action']);
        $this->assertArrayHasKey('php.functions.fqn', $data['data']);
        $this->assertArrayNotHasKey('php.classes.fqn', $data['data']);
    }

    #[TestDox('POST /api/batch with no indexes returns 400')]
    public function testBatchNoIndexes(): void
    {
        $response = $this->postJson('/api/batch', ['action' => 'clear', 'indexes' => []]);

        $this->assertSame(400, $response->getStatusCode());
    }

    #[TestDox('POST /api/batch with unknown index returns 404')]
    public function testBatchUnknownIndex(): void
    {
        $response = $this->postJson('/api/batch', ['action' => 'clear', 'indexes' => ['nonexistent']]);

        $this->assertSame(404, $response->getStatusCode());
    }

    #[TestDox('POST /api/batch with unknown action returns 400')]
    public function testBatchUnknownAction(): void
    {
        $response = $this->postJson('/api/batch', ['action' => 'foobar', 'indexes' => ['php.classes.fqn']]);

        $this->assertSame(400, $response->getStatusCode());
    }

    #[TestDox('POST /api/batch reindex without indexer returns 503')]
    public function testBatchReindexWithoutIndexer(): void
    {
        $response = $this->postJson('/api/batch', ['action' => 'reindex', 'indexes' => ['php.classes.fqn']]);

        $this->assertSame(503, $response->getStatusCode());
    }

    // --- Status API ---

    #[TestDox('GET /api/status returns indexing status')]
    public function testApiStatus(): void
    {
        $response = $this->request('GET', '/api/status');
        $data = json_decode((string) $response->getBody(), true);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertArrayHasKey('indexing', $data);
        $this->assertArrayHasKey('filesIndexed', $data);
        $this->assertFalse($data['indexing']);
    }

    // --- File browser API ---

    #[TestDox('GET /api/files returns all unique URIs')]
    public function testApiFiles(): void
    {
        $response = $this->request('GET', '/api/files');
        $data = json_decode((string) $response->getBody(), true);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame(2, $data['total']);
        $this->assertContains('file:///src/Foo.php', $data['uris']);
        $this->assertContains('file:///src/functions.php', $data['uris']);
    }

    #[TestDox('GET /api/files with pattern filters results')]
    public function testApiFilesWithPattern(): void
    {
        $response = $this->request('GET', '/api/files?pattern=Foo');
        $data = json_decode((string) $response->getBody(), true);

        $this->assertSame(1, $data['total']);
        $this->assertContains('file:///src/Foo.php', $data['uris']);
    }

    #[TestDox('GET /api/files/{uri} returns index details for a file')]
    public function testApiFileDetail(): void
    {
        $response = $this->request('GET', '/api/files/' . urlencode('file:///src/Foo.php'));
        $data = json_decode((string) $response->getBody(), true);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('file:///src/Foo.php', $data['uri']);
        $this->assertArrayHasKey('php.classes.fqn', $data['indexes']);
        $this->assertSame(3, $data['indexes']['php.classes.fqn']['count']);
    }

    #[TestDox('GET /api/files/{uri} returns error for unknown URI')]
    public function testApiFileDetailUnknown(): void
    {
        $response = $this->request('GET', '/api/files/' . urlencode('file:///nonexistent.php'));
        $data = json_decode((string) $response->getBody(), true);

        $this->assertArrayHasKey('error', $data);
    }

    // --- Autocomplete API ---

    #[TestDox('GET /api/autocomplete/keys returns matching keys')]
    public function testApiAutocompleteKeys(): void
    {
        $response = $this->request('GET', '/api/autocomplete/keys?index=php.classes.fqn&q=Foo');
        $data = json_decode((string) $response->getBody(), true);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertContains('App\\Foo', $data);
    }

    #[TestDox('GET /api/autocomplete/keys without index returns empty')]
    public function testApiAutocompleteKeysNoIndex(): void
    {
        $response = $this->request('GET', '/api/autocomplete/keys?q=Foo');
        $data = json_decode((string) $response->getBody(), true);

        $this->assertSame([], $data);
    }

    #[TestDox('GET /api/autocomplete/uris returns matching URIs')]
    public function testApiAutocompleteUris(): void
    {
        $response = $this->request('GET', '/api/autocomplete/uris?q=Foo');
        $data = json_decode((string) $response->getBody(), true);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertContains('file:///src/Foo.php', $data);
    }

    // --- File browser views ---

    #[TestDox('GET /views/files returns file list')]
    public function testViewFiles(): void
    {
        $response = $this->request('GET', '/views/files');

        $this->assertSame(200, $response->getStatusCode());
        $body = (string) $response->getBody();
        $this->assertStringContainsString('Foo.php', $body);
        $this->assertStringContainsString('functions.php', $body);
    }

    #[TestDox('GET /views/files/{uri} returns file detail')]
    public function testViewFileDetail(): void
    {
        $response = $this->request('GET', '/views/files/' . urlencode('file:///src/Foo.php'));

        $this->assertSame(200, $response->getStatusCode());
        $body = (string) $response->getBody();
        $this->assertStringContainsString('file:///src/Foo.php', $body);
        $this->assertStringContainsString('php.classes.fqn', $body);
    }

    // --- CORS ---

    #[TestDox('API responses include CORS header')]
    public function testCorsHeader(): void
    {
        $response = $this->request('GET', '/api/indexes');
        $this->assertSame('*', $response->getHeaderLine('Access-Control-Allow-Origin'));
    }
}

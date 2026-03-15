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

    // --- CORS ---

    #[TestDox('API responses include CORS header')]
    public function testCorsHeader(): void
    {
        $response = $this->request('GET', '/api/indexes');
        $this->assertSame('*', $response->getHeaderLine('Access-Control-Allow-Origin'));
    }
}

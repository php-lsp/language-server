<?php

declare(strict_types=1);

namespace App\Module\Debug;

use App\Module\Indexing\Storage\DebugStorageInterface;
use Psr\Http\Message\ServerRequestInterface;
use React\Http\HttpServer;
use React\Http\Message\Response;
use React\Socket\SocketServer;

class DebugHttpServer
{
    private ?SocketServer $socket = null;

    public function __construct(
        private readonly DebugStorageInterface $storage,
        private readonly DebugHtmlRenderer $renderer,
    ) {}

    public function start(string $host, int $port): void
    {
        $http = new HttpServer($this->handleRequest(...));

        $this->socket = new SocketServer("{$host}:{$port}");
        $http->listen($this->socket);
    }

    public function stop(): void
    {
        $this->socket?->close();
        $this->socket = null;
    }

    public function getAddress(): ?string
    {
        return $this->socket?->getAddress();
    }

    private function handleRequest(ServerRequestInterface $request): Response
    {
        $path = $request->getUri()->getPath();
        $query = $request->getQueryParams();

        return match (true) {
            $path === '/' => $this->htmlResponse($this->renderer->render()),
            $path === '/api/indexes' => $this->jsonResponse($this->apiIndexes()),
            str_starts_with($path, '/api/indexes/') => $this->routeIndexApi($path, $query),
            default => $this->jsonResponse(['error' => 'Not found'], 404),
        };
    }

    /**
     * GET /api/indexes
     *
     * @return array<string, array{key: string, count: int}>
     */
    private function apiIndexes(): array
    {
        return $this->storage->stats();
    }

    /**
     * Routes /api/indexes/{name}/...
     *
     * @param array<string, string> $query
     */
    private function routeIndexApi(string $path, array $query): Response
    {
        // Parse: /api/indexes/{name}[/keys|/search|/entries/{key}]
        $prefix = '/api/indexes/';
        $rest = substr($path, strlen($prefix));

        if ($rest === '' || $rest === false) {
            return $this->jsonResponse(['error' => 'Index name required'], 400);
        }

        // Check for sub-routes: /keys, /search, /entries/{key}
        $parts = explode('/', $rest, 3);
        $indexName = urldecode($parts[0]);
        $action = $parts[1] ?? null;
        $actionParam = isset($parts[2]) ? urldecode($parts[2]) : null;

        $indexKeys = $this->storage->getIndexKeys();

        if (!in_array($indexName, $indexKeys, true)) {
            return $this->jsonResponse([
                'error' => "Index '{$indexName}' not found",
                'available' => $indexKeys,
            ], 404);
        }

        return match ($action) {
            null => $this->jsonResponse($this->apiIndexDetail($indexName)),
            'keys' => $this->jsonResponse($this->apiKeys($indexName, $query)),
            'search' => $this->jsonResponse($this->apiSearch($indexName, $query)),
            'entries' => $this->jsonResponse($this->apiEntry($indexName, $actionParam ?? '')),
            default => $this->jsonResponse(['error' => "Unknown action: {$action}"], 404),
        };
    }

    /**
     * GET /api/indexes/{name}
     *
     * @return array{key: string, count: int}
     */
    private function apiIndexDetail(string $indexName): array
    {
        return [
            'key' => $indexName,
            'count' => $this->storage->count($indexName),
        ];
    }

    /**
     * GET /api/indexes/{name}/keys?pattern=&limit=&offset=
     *
     * @param array<string, string> $query
     * @return array<string, mixed>
     */
    private function apiKeys(string $indexName, array $query): array
    {
        $pattern = $query['pattern'] ?? '';
        $limit = max(1, (int) ($query['limit'] ?? 100));
        $offset = max(0, (int) ($query['offset'] ?? 0));

        $keys = [];

        foreach ($this->storage->read($indexName) as $entry) {
            if ($pattern !== '' && !fnmatch($pattern, $entry->key, \FNM_CASEFOLD | \FNM_NOESCAPE)) {
                continue;
            }
            $keys[] = $entry->key;
        }

        $total = count($keys);
        $keys = array_slice($keys, $offset, $limit);

        return [
            'index' => $indexName,
            'total' => $total,
            'offset' => $offset,
            'limit' => $limit,
            'keys' => $keys,
        ];
    }

    /**
     * GET /api/indexes/{name}/search?pattern=&limit=
     *
     * @param array<string, string> $query
     * @return array<string, mixed>
     */
    private function apiSearch(string $indexName, array $query): array
    {
        $pattern = $query['pattern'] ?? '*';
        $limit = max(1, (int) ($query['limit'] ?? 100));

        $results = [];
        $count = 0;

        foreach ($this->storage->search($indexName, $pattern) as $entry) {
            if ($count >= $limit) {
                break;
            }

            $results[] = [
                'key' => $entry->key,
                'value' => $this->summarizeValue($entry->value),
                'uri' => $entry->uri,
            ];
            $count++;
        }

        return [
            'index' => $indexName,
            'pattern' => $pattern,
            'count' => $count,
            'results' => $results,
        ];
    }

    /**
     * GET /api/indexes/{name}/entries/{key}
     *
     * @return array<string, mixed>
     */
    private function apiEntry(string $indexName, string $entryKey): array
    {
        $entry = $this->storage->find($indexName, $entryKey);

        if ($entry === null) {
            return ['error' => "Key '{$entryKey}' not found in index '{$indexName}'"];
        }

        return [
            'key' => $entry->key,
            'value' => $this->serializeValue($entry->value),
            'uri' => $entry->uri,
        ];
    }

    private function serializeValue(mixed $value): mixed
    {
        if (is_scalar($value) || $value === null) {
            return $value;
        }

        if (is_array($value)) {
            return array_map($this->serializeValue(...), $value);
        }

        if (is_object($value)) {
            $result = ['__class' => $value::class];
            $ref = new \ReflectionObject($value);

            foreach ($ref->getProperties() as $property) {
                $result[$property->getName()] = $this->serializeValue($property->getValue($value));
            }

            return $result;
        }

        return '(' . get_debug_type($value) . ')';
    }

    private function summarizeValue(mixed $value): mixed
    {
        if (is_scalar($value) || $value === null) {
            return $value;
        }

        if (is_array($value)) {
            return '(array[' . count($value) . '])';
        }

        if (is_object($value)) {
            return '(' . $value::class . ')';
        }

        return '(' . get_debug_type($value) . ')';
    }

    private function jsonResponse(mixed $data, int $status = 200): Response
    {
        return new Response(
            $status,
            ['Content-Type' => 'application/json', 'Access-Control-Allow-Origin' => '*'],
            (string) json_encode($data, \JSON_PRETTY_PRINT | \JSON_UNESCAPED_UNICODE | \JSON_UNESCAPED_SLASHES),
        );
    }

    private function htmlResponse(string $html): Response
    {
        return new Response(
            200,
            ['Content-Type' => 'text/html; charset=utf-8'],
            $html,
        );
    }
}

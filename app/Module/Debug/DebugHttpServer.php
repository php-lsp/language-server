<?php

declare(strict_types=1);

namespace App\Module\Debug;

use App\Module\Indexing\Indexer;
use App\Module\Indexing\IndexingStatus;
use App\Module\Indexing\Storage\DebugStorageInterface;
use App\Module\Workspace\ProjectManager;
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
        private readonly ?Indexer $indexer = null,
        private readonly ?ProjectManager $projectManager = null,
        private readonly ?IndexingStatus $indexingStatus = null,
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
        $method = $request->getMethod();

        return match (true) {
            // HTML views (HTMX fragments + full page)
            $path === '/' => $this->htmlResponse($this->renderer->layout()),
            $path === '/views/indexes' => $this->htmlResponse($this->renderer->indexList()),
            $path === '/views/search' => $this->htmlResponse($this->renderer->globalSearch($query)),
            $path === '/views/files' => $this->htmlResponse($this->renderer->fileList($query)),
            str_starts_with($path, '/views/files/') => $this->htmlResponse(
                $this->renderer->fileDetail(urldecode(substr($path, strlen('/views/files/')))),
            ),
            str_starts_with($path, '/views/indexes/') => $this->routeView($path, $query),

            // JSON API (for curl / agents)
            $path === '/api/indexes' => $this->jsonResponse($this->apiIndexes()),
            $path === '/api/export' => $this->jsonResponse($this->apiExport()),
            $path === '/api/search' => $this->jsonResponse($this->apiGlobalSearch($query)),
            $path === '/api/status' => $this->jsonResponse($this->apiStatus()),
            $path === '/api/files' => $this->jsonResponse($this->apiFiles($query)),
            str_starts_with($path, '/api/files/') => $this->jsonResponse(
                $this->apiFileDetail(urldecode(substr($path, strlen('/api/files/')))),
            ),
            $path === '/api/autocomplete/keys' => $this->jsonResponse($this->apiAutocompleteKeys($query)),
            $path === '/api/autocomplete/uris' => $this->jsonResponse($this->apiAutocompleteUris($query)),
            $path === '/api/batch' && $method === 'POST' => $this->handleBatch($request),
            str_starts_with($path, '/api/indexes/') => $this->routeIndexApi($path, $query),

            default => $this->htmlResponse('<div class="empty">Not found.</div>', 404),
        };
    }

    // --- HTML view routes ---

    /**
     * @param array<string, string> $query
     */
    private function routeView(string $path, array $query): Response
    {
        $prefix = '/views/indexes/';
        $rest = substr($path, strlen($prefix));

        if ($rest === '' || $rest === false) {
            return $this->htmlResponse('<div class="empty">Index name required.</div>', 400);
        }

        $parts = explode('/', $rest, 3);
        $indexName = urldecode($parts[0]);
        $action = $parts[1] ?? 'keys';
        $actionParam = isset($parts[2]) ? urldecode($parts[2]) : null;

        $indexKeys = $this->storage->getIndexKeys();

        if (!in_array($indexName, $indexKeys, true)) {
            return $this->htmlResponse('<div class="empty">Index not found.</div>', 404);
        }

        return match ($action) {
            'keys' => $this->htmlResponse($this->renderer->keysList($indexName, $query)),
            'entries' => $this->htmlResponse($this->renderer->entryDetail($indexName, $actionParam ?? '')),
            default => $this->htmlResponse('<div class="empty">Unknown action.</div>', 404),
        };
    }

    // --- Batch actions ---

    private function handleBatch(ServerRequestInterface $request): Response
    {
        $body = json_decode((string) $request->getBody(), true);

        if (!is_array($body)) {
            return $this->jsonResponse(['error' => 'Invalid JSON body'], 400);
        }

        $action = $body['action'] ?? '';
        /** @var list<string> $indexes */
        $indexes = $body['indexes'] ?? [];

        if ($indexes === []) {
            return $this->jsonResponse(['error' => 'No indexes selected'], 400);
        }

        // Validate indexes exist
        $validKeys = $this->storage->getIndexKeys();
        $invalid = array_diff($indexes, $validKeys);

        if ($invalid !== []) {
            return $this->jsonResponse([
                'error' => 'Unknown indexes: ' . implode(', ', $invalid),
                'available' => $validKeys,
            ], 404);
        }

        return match ($action) {
            'clear' => $this->batchClear($indexes),
            'reindex' => $this->batchReindex($indexes),
            'export' => $this->batchExport($indexes),
            default => $this->jsonResponse(['error' => "Unknown action: {$action}"], 400),
        };
    }

    /**
     * @param list<string> $indexes
     */
    private function batchClear(array $indexes): Response
    {
        $this->storage->clear($indexes);

        return $this->jsonResponse([
            'action' => 'clear',
            'indexes' => $indexes,
            'status' => 'ok',
        ]);
    }

    /**
     * @param list<string> $indexes
     */
    private function batchReindex(array $indexes): Response
    {
        if ($this->indexer === null || $this->projectManager === null) {
            return $this->jsonResponse(['error' => 'Reindexing is not available'], 503);
        }

        $this->storage->clear($indexes);
        $this->indexer->index($this->projectManager->getProject());

        return $this->jsonResponse([
            'action' => 'reindex',
            'indexes' => $indexes,
            'status' => 'ok',
        ]);
    }

    /**
     * @param list<string> $indexes
     */
    private function batchExport(array $indexes): Response
    {
        $export = [];

        foreach ($indexes as $indexKey) {
            $entries = [];
            foreach ($this->storage->read($indexKey) as $entry) {
                $entries[] = [
                    'key' => $entry->key,
                    'value' => $this->serializeValue($entry->value),
                    'uri' => $entry->uri,
                ];
            }
            $export[$indexKey] = $entries;
        }

        return $this->jsonResponse([
            'action' => 'export',
            'indexes' => $indexes,
            'data' => $export,
        ]);
    }

    // --- JSON API routes ---

    /**
     * @return array<string, array{key: string, count: int}>
     */
    private function apiIndexes(): array
    {
        return $this->storage->stats();
    }

    /**
     * @param array<string, string> $query
     */
    private function routeIndexApi(string $path, array $query): Response
    {
        $prefix = '/api/indexes/';
        $rest = substr($path, strlen($prefix));

        if ($rest === '' || $rest === false) {
            return $this->jsonResponse(['error' => 'Index name required'], 400);
        }

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
     * @return array<string, mixed>
     */
    private function apiExport(): array
    {
        $export = [];

        foreach ($this->storage->getIndexKeys() as $indexKey) {
            $entries = [];
            foreach ($this->storage->read($indexKey) as $entry) {
                $entries[] = [
                    'key' => $entry->key,
                    'value' => $this->serializeValue($entry->value),
                    'uri' => $entry->uri,
                ];
            }
            $export[$indexKey] = $entries;
        }

        return $export;
    }

    /**
     * @param array<string, string> $query
     * @return array<string, mixed>
     */
    private function apiGlobalSearch(array $query): array
    {
        $pattern = $query['pattern'] ?? '*';
        $limit = max(1, (int) ($query['limit'] ?? 100));

        // Auto-wrap pattern with wildcards if no glob chars present
        if ($pattern !== '' && !str_contains($pattern, '*') && !str_contains($pattern, '?')) {
            $pattern = '*' . $pattern . '*';
        }

        $results = [];

        foreach ($this->storage->searchAll($pattern, $limit) as $hit) {
            $results[] = [
                'index' => $hit['index'],
                'key' => $hit['key'],
                'value' => $this->summarizeValue($hit['value']),
                'uri' => $hit['uri'],
            ];
        }

        return [
            'pattern' => $pattern,
            'count' => count($results),
            'results' => $results,
        ];
    }

    /**
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

    // --- Status ---

    /**
     * @return array<string, mixed>
     */
    private function apiStatus(): array
    {
        return $this->indexingStatus?->toArray() ?? [
            'indexing' => false,
            'filesIndexed' => 0,
            'lastIndexedAt' => null,
            'lastDuration' => null,
        ];
    }

    // --- File browser ---

    /**
     * @param array<string, string> $query
     * @return array<string, mixed>
     */
    private function apiFiles(array $query): array
    {
        $pattern = $query['pattern'] ?? '';
        $limit = max(1, (int) ($query['limit'] ?? 200));

        $uris = $this->storage->getUris();

        if ($pattern !== '') {
            $searchPattern = $pattern;
            if (!str_contains($searchPattern, '*') && !str_contains($searchPattern, '?')) {
                $searchPattern = '*' . $searchPattern . '*';
            }

            $uris = array_values(array_filter(
                $uris,
                static fn(string $uri): bool => fnmatch($searchPattern, $uri, \FNM_CASEFOLD | \FNM_NOESCAPE),
            ));
        }

        $total = count($uris);
        $uris = array_slice($uris, 0, $limit);

        return [
            'total' => $total,
            'limit' => $limit,
            'uris' => $uris,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function apiFileDetail(string $uri): array
    {
        $entries = $this->storage->findByUri($uri);

        if ($entries === []) {
            return ['error' => "No entries found for URI '{$uri}'"];
        }

        $indexes = [];
        foreach ($entries as $indexKey => $indexEntries) {
            $indexes[$indexKey] = [
                'count' => count($indexEntries),
                'keys' => array_map(static fn($e) => $e->key, $indexEntries),
            ];
        }

        return [
            'uri' => $uri,
            'indexCount' => count($indexes),
            'indexes' => $indexes,
        ];
    }

    // --- Autocomplete ---

    /**
     * @param array<string, string> $query
     * @return list<string>
     */
    private function apiAutocompleteKeys(array $query): array
    {
        $q = $query['q'] ?? '';
        $index = $query['index'] ?? '';
        $limit = max(1, (int) ($query['limit'] ?? 50));

        if ($index === '') {
            return [];
        }

        $results = [];
        $count = 0;

        foreach ($this->storage->read($index) as $entry) {
            if ($count >= $limit) {
                break;
            }

            if ($q === '' || stripos($entry->key, $q) !== false) {
                $results[] = $entry->key;
                $count++;
            }
        }

        return $results;
    }

    /**
     * @param array<string, string> $query
     * @return list<string>
     */
    private function apiAutocompleteUris(array $query): array
    {
        $q = $query['q'] ?? '';
        $limit = max(1, (int) ($query['limit'] ?? 50));

        $uris = $this->storage->getUris();
        $results = [];

        foreach ($uris as $uri) {
            if (count($results) >= $limit) {
                break;
            }

            if ($q === '' || stripos($uri, $q) !== false) {
                $results[] = $uri;
            }
        }

        return $results;
    }

    private function jsonResponse(mixed $data, int $status = 200): Response
    {
        return new Response(
            $status,
            ['Content-Type' => 'application/json', 'Access-Control-Allow-Origin' => '*'],
            (string) json_encode($data, \JSON_PRETTY_PRINT | \JSON_UNESCAPED_UNICODE | \JSON_UNESCAPED_SLASHES),
        );
    }

    private function htmlResponse(string $html, int $status = 200): Response
    {
        return new Response(
            $status,
            ['Content-Type' => 'text/html; charset=utf-8'],
            $html,
        );
    }
}

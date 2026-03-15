# Debug Index Inspector

Built-in HTTP server for browsing and debugging the LSP server's in-memory indexes at runtime.

## Starting

The debug server starts **automatically** alongside the LSP server on port **LSP_PORT + 1**.
The URL is printed to STDERR on startup.

```bash
# LSP on port 5007, debug on port 5008
./bin/lsp serve --port=5007

# Or set the debug server port explicitly
LSP_DEBUG_PORT=9090 ./bin/lsp serve --port=5007
```

Open in a browser:

```
http://127.0.0.1:5008
```

## Web Interface

Server-rendered UI powered by [HTMX](https://htmx.org) with a dark theme. All navigation uses
HTMX partial page loads — no full-page reloads.

### Index List (home page)

- Table of all registered indexes with columns: **name**, **entry count**, **memory usage**
- **Global search** — input field with 300ms debounce auto-search across all indexes
- **Batch actions** — checkboxes on each row with select-all; action buttons:
  - **Clear** — remove all entries from selected indexes
  - **Reindex** — clear and re-index selected indexes from the project
  - **Export JSON** — download selected indexes as a JSON file
- **Export JSON** button in the header — exports all index data

### Keys List

Click an index to see all its keys. Features:
- Glob filter with auto-wildcards (typing `Controller` searches for `*Controller*`)
- Pagination (50 entries per page)

### Entry Detail

Click a key to see: key, value (with deep object serialization), source file URI.

## HTTP API

All endpoints return JSON. CORS is enabled (`Access-Control-Allow-Origin: *`).

### `GET /api/indexes`

List all indexes with statistics (count and memory usage).

```bash
curl http://127.0.0.1:5008/api/indexes
```

```json
{
  "php.classes.fqn": { "key": "php.classes.fqn", "count": 142, "memory": 45832 },
  "php.functions.fqn": { "key": "php.functions.fqn", "count": 87, "memory": 21504 }
}
```

### `GET /api/indexes/{name}`

Details of a specific index.

```bash
curl http://127.0.0.1:5008/api/indexes/php.classes.fqn
```

```json
{ "key": "php.classes.fqn", "count": 142 }
```

### `GET /api/indexes/{name}/keys`

Index keys with filtering and pagination.

| Parameter | Type   | Default | Description                                |
|-----------|--------|---------|--------------------------------------------|
| `pattern` | string | —       | Glob pattern (e.g. `App\Controller\*`)     |
| `limit`   | int    | 100     | Maximum entries                            |
| `offset`  | int    | 0       | Offset for pagination                      |

```bash
curl 'http://127.0.0.1:5008/api/indexes/php.classes.fqn/keys?pattern=App\Controller\*&limit=10'
```

```json
{
  "index": "php.classes.fqn",
  "total": 5,
  "offset": 0,
  "limit": 10,
  "keys": [
    "App\\Controller\\HomeController",
    "App\\Controller\\InitializeController"
  ]
}
```

### `GET /api/indexes/{name}/search`

Search entries by key glob pattern. Returns summarized values.

| Parameter | Type   | Default | Description      |
|-----------|--------|---------|------------------|
| `pattern` | string | `*`     | Glob pattern     |
| `limit`   | int    | 100     | Maximum entries  |

```bash
curl 'http://127.0.0.1:5008/api/indexes/php.classes.fqn/search?pattern=*Controller*'
```

```json
{
  "index": "php.classes.fqn",
  "pattern": "*Controller*",
  "count": 3,
  "results": [
    { "key": "App\\Controller\\HomeController", "value": "App\\Controller\\HomeController", "uri": "file:///src/Controller/HomeController.php" }
  ]
}
```

### `GET /api/indexes/{name}/entries/{key}`

Full entry detail with deep object serialization.

```bash
curl 'http://127.0.0.1:5008/api/indexes/php.classes.fqn/entries/App%5CController%5CHomeController'
```

```json
{
  "key": "App\\Controller\\HomeController",
  "value": "App\\Controller\\HomeController",
  "uri": "file:///src/Controller/HomeController.php"
}
```

For object values (e.g. indexers storing data structures):

```json
{
  "key": "App\\Service\\UserService",
  "value": {
    "__class": "App\\Module\\Indexing\\Data\\ClassInfo",
    "name": "UserService",
    "namespace": "App\\Service",
    "isAbstract": false
  },
  "uri": "file:///src/Service/UserService.php"
}
```

### `GET /api/export`

Export all index data as JSON. Contains every entry from every index.

```bash
curl http://127.0.0.1:5008/api/export
```

```json
{
  "php.classes.fqn": [
    { "key": "App\\Foo", "value": "App\\Foo", "uri": "file:///src/Foo.php" }
  ],
  "php.functions.fqn": [
    { "key": "App\\hello", "value": "App\\hello", "uri": "file:///src/functions.php" }
  ]
}
```

### `GET /api/search`

Global search across all indexes by key pattern. Auto-wraps plain text with
wildcards (typing `Controller` searches for `*Controller*`).

| Parameter | Type   | Default | Description      |
|-----------|--------|---------|------------------|
| `pattern` | string | `*`     | Glob pattern     |
| `limit`   | int    | 100     | Maximum entries  |

```bash
curl 'http://127.0.0.1:5008/api/search?pattern=Controller'
```

```json
{
  "pattern": "*Controller*",
  "count": 3,
  "results": [
    { "index": "php.classes.fqn", "key": "App\\Controller\\HomeController", "value": "App\\Controller\\HomeController", "uri": "file:///src/Controller/HomeController.php" }
  ]
}
```

### `POST /api/batch`

Batch actions on selected indexes. Send a JSON body with `action` and `indexes`.

| Field     | Type         | Description                                  |
|-----------|--------------|----------------------------------------------|
| `action`  | string       | One of: `clear`, `reindex`, `export`         |
| `indexes` | list<string> | Index keys to act on                         |

**Clear** — removes all entries from selected indexes:

```bash
curl -X POST http://127.0.0.1:5008/api/batch \
  -H 'Content-Type: application/json' \
  -d '{"action": "clear", "indexes": ["php.classes.fqn"]}'
```

```json
{ "action": "clear", "indexes": ["php.classes.fqn"], "status": "ok" }
```

**Reindex** — clears and re-indexes the entire project (requires Indexer and ProjectManager):

```bash
curl -X POST http://127.0.0.1:5008/api/batch \
  -H 'Content-Type: application/json' \
  -d '{"action": "reindex", "indexes": ["php.classes.fqn"]}'
```

```json
{ "action": "reindex", "indexes": ["php.classes.fqn"], "status": "ok" }
```

**Export** — returns full data for selected indexes:

```bash
curl -X POST http://127.0.0.1:5008/api/batch \
  -H 'Content-Type: application/json' \
  -d '{"action": "export", "indexes": ["php.classes.fqn"]}'
```

```json
{
  "action": "export",
  "indexes": ["php.classes.fqn"],
  "data": {
    "php.classes.fqn": [
      { "key": "App\\Foo", "value": "App\\Foo", "uri": "file:///src/Foo.php" }
    ]
  }
}
```

## Auto-Wildcards

Both the web UI and the global search API automatically wrap plain text with
wildcards. Typing `token` is equivalent to searching for `*token*`. If the
pattern already contains `*` or `?`, it is used as-is.

## LSP Methods (alternative access)

Debug data is also available via LSP JSON-RPC requests (through IDE clients):

| Method               | Parameters                      | Description        |
|----------------------|---------------------------------|--------------------|
| `debug/index/list`   | —                               | List indexes       |
| `debug/index/get`    | `{index, key}`                  | Entry by key       |
| `debug/index/search` | `{pattern, index?, limit?}`     | Search by pattern  |
| `debug/index/keys`   | `{index, pattern?, limit?, offset?}` | Index keys    |

## Usage from an LLM Agent

An agent can use the debug server for runtime index inspection via HTTP.

### Check if a class is indexed

```bash
curl -s http://127.0.0.1:5008/api/indexes/php.classes.fqn/entries/App%5CFoo | jq .
```

### Find all controllers

```bash
curl -s 'http://127.0.0.1:5008/api/search?pattern=Controller' | jq '.results[].key'
```

### List all available indexes

```bash
curl -s http://127.0.0.1:5008/api/indexes | jq 'to_entries[] | "\(.key): \(.value.count) entries"'
```

### Debug why autocomplete is not working

```bash
# 1. Check that the index is populated
curl -s http://127.0.0.1:5008/api/indexes | jq .

# 2. Search for a specific symbol
curl -s 'http://127.0.0.1:5008/api/search?pattern=myFunc' | jq .

# 3. View full entry data
curl -s 'http://127.0.0.1:5008/api/indexes/php.functions.fqn/entries/App%5CmyFunc' | jq .
```

## Architecture

```
LSP Server (port 5007)          Debug HTTP Server (port 5008)
     │                                    │
     │   ┌──────────────────┐             │
     └──>│  InMemoryStorage │<────────────┘
          │  (shared)       │
          │                 │
          │  php.classes.fqn│
          │  php.functions  │
          │  php.interfaces │
          │  ...            │
          └─────────────────┘
```

Both servers run on the same ReactPHP event loop and share the same `InMemoryStorage` instance.

### Key Classes

| Class                    | File                                          | Role                              |
|--------------------------|-----------------------------------------------|-----------------------------------|
| `DebugHttpServer`        | `app/Module/Debug/DebugHttpServer.php`         | HTTP routing and API handlers     |
| `DebugHtmlRenderer`      | `app/Module/Debug/DebugHtmlRenderer.php`       | Server-rendered HTMX views        |
| `DebugServerListener`    | `app/Listener/DebugServerListener.php`         | Starts debug server on LSP start  |
| `DebugStorageInterface`  | `app/Module/Indexing/Storage/DebugStorageInterface.php` | Extended storage with introspection |
| `InMemoryStorage`        | `app/Module/Indexing/Storage/InMemoryStorage.php`      | Storage implementation            |

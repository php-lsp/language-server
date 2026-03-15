# Debug Index Inspector

Встроенный HTTP-сервер для просмотра и отладки in-memory индексов LSP-сервера.

## Запуск

Debug-сервер стартует **автоматически** вместе с LSP-сервером на порту **LSP_PORT + 1**.

```bash
# LSP на порту 5007, debug на порту 5008
./bin/lsp serve --port=5007

# Или задать порт debug-сервера явно
LSP_DEBUG_PORT=9090 ./bin/lsp serve --port=5007
```

После запуска открой в браузере:

```
http://127.0.0.1:5008
```

## Веб-интерфейс

Одностраничное приложение (SPA) с тёмной темой:

1. **Список индексов** — таблица всех зарегистрированных индексов с количеством записей
2. **Ключи индекса** — кликни по индексу, чтобы увидеть все ключи. Поддерживает:
   - Glob-фильтр (например `App\Controller\*`)
   - Пагинацию (50 записей на страницу)
3. **Детали записи** — кликни по ключу, чтобы увидеть полное содержимое: key, value (с deep-сериализацией объектов), URI исходного файла

## HTTP API

Все endpoints возвращают JSON. CORS включён (`Access-Control-Allow-Origin: *`).

### `GET /api/indexes`

Список всех индексов со статистикой.

```bash
curl http://127.0.0.1:5008/api/indexes
```

```json
{
  "php.classes.fqn": { "key": "php.classes.fqn", "count": 142 },
  "php.functions.fqn": { "key": "php.functions.fqn", "count": 87 },
  "php.interfaces.fqn": { "key": "php.interfaces.fqn", "count": 23 }
}
```

### `GET /api/indexes/{name}`

Детали конкретного индекса.

```bash
curl http://127.0.0.1:5008/api/indexes/php.classes.fqn
```

```json
{ "key": "php.classes.fqn", "count": 142 }
```

### `GET /api/indexes/{name}/keys`

Ключи индекса с фильтрацией и пагинацией.

| Параметр | Тип | По умолчанию | Описание |
|----------|-----|--------------|----------|
| `pattern` | string | — | Glob-паттерн (например `App\Controller\*`) |
| `limit` | int | 100 | Максимум записей |
| `offset` | int | 0 | Смещение для пагинации |

```bash
# Все классы в неймспейсе App\Controller
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
    "App\\Controller\\InitializeController",
    "App\\Controller\\InitializedController"
  ]
}
```

### `GET /api/indexes/{name}/search`

Поиск записей по glob-паттерну ключа. Возвращает краткое описание значений.

| Параметр | Тип | По умолчанию | Описание |
|----------|-----|--------------|----------|
| `pattern` | string | `*` | Glob-паттерн |
| `limit` | int | 100 | Максимум записей |

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

Полная информация о конкретной записи с deep-сериализацией объектов.

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

Для объектных значений (например, из indexer'ов, которые хранят структуры):

```json
{
  "key": "App\\Service\\UserService",
  "value": {
    "__class": "App\\Module\\Indexing\\Data\\ClassInfo",
    "name": "UserService",
    "namespace": "App\\Service",
    "methods": ["findById", "create", "delete"],
    "isAbstract": false
  },
  "uri": "file:///src/Service/UserService.php"
}
```

## Использование из LLM-агента

Агент может использовать debug-сервер для инспекции состояния индексов через HTTP.

### Сценарий: проверить, что класс проиндексирован

```bash
# Проверить, есть ли класс в индексе
curl -s http://127.0.0.1:5008/api/indexes/php.classes.fqn/entries/App%5CFoo | jq .
```

### Сценарий: найти все контроллеры

```bash
curl -s 'http://127.0.0.1:5008/api/indexes/php.classes.fqn/search?pattern=*Controller*' | jq '.results[].key'
```

### Сценарий: посмотреть, какие индексы существуют

```bash
curl -s http://127.0.0.1:5008/api/indexes | jq 'to_entries[] | "\(.key): \(.value.count) entries"'
```

### Сценарий: отладить, почему autocomplete не работает

```bash
# 1. Проверить, что индекс вообще заполнен
curl -s http://127.0.0.1:5008/api/indexes | jq .

# 2. Поискать конкретный символ
curl -s 'http://127.0.0.1:5008/api/indexes/php.functions.fqn/search?pattern=*myFunc*' | jq .

# 3. Посмотреть полные данные записи
curl -s 'http://127.0.0.1:5008/api/indexes/php.functions.fqn/entries/App%5CmyFunc' | jq .
```

## LSP-методы (альтернативный доступ)

Debug-данные также доступны через LSP JSON-RPC запросы (через клиент IDE):

| Метод | Параметры | Описание |
|-------|-----------|----------|
| `debug/index/list` | — | Список индексов |
| `debug/index/get` | `{index, key}` | Запись по ключу |
| `debug/index/search` | `{pattern, index?, limit?}` | Поиск по паттерну |
| `debug/index/keys` | `{index, pattern?, limit?, offset?}` | Ключи индекса |

## Архитектура

```
LSP Server (port 5007)          Debug HTTP Server (port 5008)
     │                                    │
     │   ┌──────────────────┐             │
     └──►│  InMemoryStorage │◄────────────┘
          │  (shared)       │
          │                 │
          │  php.classes.fqn│
          │  php.functions  │
          │  php.interfaces │
          │  ...            │
          └─────────────────┘
```

Оба сервера работают на одном ReactPHP event loop и обращаются к одному и тому же `InMemoryStorage`.

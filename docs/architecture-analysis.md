# Architecture Analysis: PHP Language Server

> Объективный анализ архитектуры, выявление слабых мест, рекомендации по
> улучшению и сравнение с ведущими LSP-реализациями.
>
> Дата: 2026-03-15

---

## Содержание

1. [Обзор текущей архитектуры](#1-обзор-текущей-архитектуры)
2. [Количественные метрики](#2-количественные-метрики)
3. [Архитектурные паттерны](#3-архитектурные-паттерны)
4. [Слабые места и проблемы](#4-слабые-места-и-проблемы)
5. [Сравнение с другими LSP/IDE](#5-сравнение-с-другими-lspide)
6. [Матрица оценки](#6-матрица-оценки)
7. [Рекомендации по улучшению](#7-рекомендации-по-улучшению)
8. [Приоритеты](#8-приоритеты)

---

## 1. Обзор текущей архитектуры

### Стек

| Компонент | Технология |
|-----------|-----------|
| Язык | PHP 8.4+ |
| DI-контейнер | Symfony DependencyInjection |
| Async I/O | ReactPHP (event loop + promises) |
| Парсер AST | nikic/php-parser v5 |
| Типы | PHPStan (type resolution) |
| Протокол | php-lsp/protocol (типизированные LSP DTOs) |
| Ядро | php-lsp/kernel (LanguageServerKernel) |

### Слои (сверху вниз)

```
LSP Client (IDE)
    │ JSON-RPC / TCP
Transport (php-lsp/kernel + ReactPHP)
    │
Routing (#[Route] атрибуты)
    │
Controllers (14 штук)
    │
Context + Contributors (параллельно) + Consumer
    │
Infrastructure: Indexing, PsiFile (AST), TypeSystem, DocumentManager
```

### Ключевые решения

- **Contributor/Plugin pattern** — контроллеры делегируют работу набору
  мелких contributor-классов, обнаруживаемых через PHP-атрибуты и DI-теги.
- **Параллельное выполнение** — completion-контрибьюторы запускаются через
  `React\Promise\all()` с timeout 1 сек.
- **In-Memory индексация** — весь индекс хранится в `InMemoryStorage`
  (PHP-массивы в памяти процесса).
- **FIFO-кеш AST** — 300 файлов, вытеснение 10% старейших при переполнении.

---

## 2. Количественные метрики

### Размер кодовой базы

| Метрика | Значение |
|---------|----------|
| PHP-файлов в `app/` | 150 |
| PHP-файлов в `tests/` | 121 |
| Строк кода (`app/`) | 8 217 |
| Отношение тестов/код | 0.81 (файлы) |

### Размер модулей (LOC)

| Модуль | LOC | Файлов | Назначение |
|--------|-----|--------|------------|
| Indexing | 1 803 | 47 | Индексация и хранилище |
| Completion | 1 395 | 17 | Автодополнение |
| References | 674 | 7 | Поиск ссылок |
| Declaration | 628 | 7 | Go-to-definition |
| PsiFile | 593 | 6 | AST-парсинг |
| Documentation | 534 | 7 | Hover-документация |
| Signature | 426 | 3 | Сигнатуры функций |
| TypeSystem | 310 | 5 | Разрешение типов |
| Controller | 1 004 | 14 | Обработчики запросов |
| Core/Contracts | 433 | 24 | Интерфейсы и контракты |

### Coupling (связанность модулей)

| Модуль | Ca (входящие) | Ce (исходящие) | Instability (Ce/(Ca+Ce)) |
|--------|:---:|:---:|:---:|
| **PsiFile** | 96 | 1 | 0.01 |
| **Indexing** | 95 | 29 | 0.23 |
| Document | 6 | 1 | 0.14 |
| Notification | 3 | 0 | 0.00 |
| Workspace | 2 | 0 | 0.00 |
| **TypeSystem** | 1 | 3 | 0.75 |
| Completion | 0 | 42 | 1.00 |
| Declaration | 0 | 37 | 1.00 |
| Documentation | 0 | 26 | 1.00 |
| References | 0 | 26 | 1.00 |
| Signature | 0 | 15 | 1.00 |

**Интерпретация:**
- **PsiFile и Indexing** — центральные модули, от которых зависит
  практически всё. Instability ≈ 0 — они стабильны, но при этом содержат
  конкретные реализации (не абстракции), что нарушает Stable Abstractions
  Principle (SAP).
- Completion, Declaration и другие — полностью нестабильные (Instability = 1),
  что правильно для конечных consumer-модулей.
- TypeSystem имеет Instability 0.75 — адекватно для модуля, который
  используется редко, но зависит от внешних библиотек.

### Abstractness (абстрактность модулей)

| Модуль | Всего классов | Интерфейсов/абстрактных | Abstractness |
|--------|:---:|:---:|:---:|
| Document | 4 | 2 | 0.50 |
| TypeSystem | 5 | 1 | 0.20 |
| Documentation | 7 | 1 | 0.14 |
| References | 7 | 1 | 0.14 |
| Indexing | 47 | 4 | 0.08 |
| Completion | 17 | 1 | 0.05 |
| PsiFile | 6 | 0 | **0.00** |
| Declaration | 7 | 0 | **0.00** |
| Signature | 3 | 0 | **0.00** |
| Workspace | 1 | 0 | **0.00** |

**Проблема:** PsiFile (Ca=96, Abstractness=0.00) попадает в «зону боли»
(Zone of Pain) по диаграмме Мартина — высоко стабильный, но конкретный.
Любое изменение в `InMemoryPsiFileManager` или `PHPPsiFile` затронет
огромное количество зависимых модулей.

---

## 3. Архитектурные паттерны

### Используемые паттерны

| Паттерн | Где применяется | Оценка |
|---------|----------------|--------|
| **Strategy** | Contributor интерфейсы (CompletionContributor, etc.) | Хорошо |
| **Observer** | Symfony EventDispatcher для событий сервера | Хорошо |
| **Template Method** | AbstractPhpIndexer (abstract `indexInternal`) | Хорошо |
| **Consumer/Collector** | CompletionConsumer, ReferenceConsumer — сбор результатов | Хорошо |
| **FIFO Cache** | FifoCache для AST-файлов | Адекватно |
| **Service Locator** | `#[AutowireIterator]` для получения списка контрибьюторов | Адекватно |
| **Repository** | StorageInterface / InMemoryStorage для индекса | Базово |

### Отсутствующие паттерны (критично для LSP)

| Паттерн | Необходимость | Статус |
|---------|--------------|--------|
| **Demand-driven computation / Incremental** | Критично | Отсутствует |
| **Cancel token / Cancellation** | Высоко | Отсутствует |
| **Virtual File System (VFS)** | Высоко | Частично (Document layer) |
| **Persistent index / Serialization** | Высоко | Отсутствует |
| **Workspace change tracking** | Высоко | Отсутствует |

---

## 4. Слабые места и проблемы

### 4.1. Индексация — полный пересчёт без инкрементальности

**Проблема:** `Indexer::index()` обходит все файлы проекта синхронно при
инициализации. Нет механизма инкрементального обновления при изменении
одного файла.

```php
// app/Module/Indexing/Indexer.php:36-48
public function index(Project $project): void
{
    foreach ($project as $file) {
        $this->walkFilesInternal($file, 0);  // Весь проект
    }
    // + PHP stubs
    $this->walkFilesInternal($stubs, 0);
}
```

**Последствия:**
- При открытии проекта с 10 000+ файлов — долгий старт.
- При изменении файла индекс не обновляется — данные устаревают.
- Нет возможности частичного переиндексирования.

**Уровень критичности:** КРИТИЧНЫЙ

### 4.2. InMemoryStorage — данные теряются при перезапуске

**Проблема:** Весь индекс хранится в PHP-массивах. Нет персистентного
хранилища. При перезапуске сервера индекс полностью теряется.

```php
// app/Module/Indexing/Storage/InMemoryStorage.php
class InMemoryStorage implements StorageInterface
{
    private array $entries = [];  // Всё в памяти
}
```

**Последствия:**
- Каждый запуск = полная переиндексация.
- На крупных проектах — неприемлемая задержка при старте.
- Потребление памяти растёт линейно с размером проекта.

**Уровень критичности:** ВЫСОКИЙ

### 4.3. Линейный поиск по индексу — O(N)

**Проблема:** Большинство contributors выполняют полный проход по всем
записям индекса и фильтруют по имени/типу:

```php
// Типичный паттерн в contributors:
foreach ($this->indexLookup->findByKey(ClassMethodIndexer::class) as $entry) {
    if ($entry->value->className !== $className) {
        continue;  // Линейная фильтрация
    }
}
```

**Последствия:**
- Completion на проекте с 50 000 методов = 50 000 итераций для каждого
  contributor'а.
- Деградация производительности при росте проекта.

**Уровень критичности:** ВЫСОКИЙ

### 4.4. Hardcoded-список игнорируемых директорий

**Проблема:** В `Indexer::walkFilesInternal()` список игнорируемых
директорий захардкожен:

```php
$ignored = [
    'node_modules', '.git', '.idea', 'config', 'resources',
    'runtime', 'psalm', 'rector', 'thecodingmachine',
    'aerospike', 'tests', 'mongodb', 'meta', 'rdkafka',
    'intl', 'swoole', 'wincache', 'couchbase', ...
];
```

**Последствия:**
- Нет конфигурируемости для пользователя.
- Некоторые записи специфичны (aerospike, couchbase) и не должны быть
  в базовом коде.
- Дублирование (`'tests'` указан дважды).

**Уровень критичности:** СРЕДНИЙ

### 4.5. Индексация отключена при инициализации

**Проблема:** В `InitializeController::walkWorkspaceFolder()` стоит
ранний `return` перед вызовом индексации:

```php
private function walkWorkspaceFolder(WorkspaceFolder $folder): void
{
    $project = $this->projectFactory->create($folder->uri, $folder->name);
    $this->projectManager->setProject($project);

    return;  // <-- Индексация отключена!
    $this->indexer->index($project);
}
```

**Последствия:**
- Индекс всегда пуст.
- Все контрибьюторы, зависящие от индекса, не работают.
- Фактически completion по классам/функциям не функционирует.

**Уровень критичности:** КРИТИЧНЫЙ

### 4.6. Отсутствие cancellation и прогресс-уведомлений

**Проблема:** Нет механизма отмены запросов. Если пользователь быстро
набирает текст, каждый `textDocument/completion` запрос выполняется
полностью, даже если результат уже не нужен.

**Последствия:**
- Нагрузка на сервер при быстрой печати.
- Задержки в ответах — IDE может показывать устаревшие результаты.

**Уровень критичности:** СРЕДНИЙ

### 4.7. PsiFile — God Object тенденция

**Проблема:** `InMemoryPsiFileManager` совмещает:
- Парсинг файлов
- Кеширование AST
- Отправку диагностик клиенту
- Загрузку документов с диска
- Инвалидацию кеша по версии

Это нарушает Single Responsibility Principle. 96 входящих зависимостей
делают рефакторинг рискованным.

**Уровень критичности:** СРЕДНИЙ

### 4.8. Закомментированный debug-код

**Проблема:** В 7+ файлах остался закомментированный debug-код
(`dump()`, `echo`, `var_dump`):

```
app/Application.php:45      // dump(...)
app/Controller/TextDocument/DeclarationController.php:33   // dump(...)
app/Controller/TextDocument/ReferencesController.php:33    // dump(...)
app/Module/PsiFile/InMemoryPsiFileManager.php:74           // dump(...)
app/Module/Indexing/Indexer.php:80                         // echo(...)
```

**Уровень критичности:** НИЗКИЙ (code smell)

### 4.9. Отсутствие Go-to-definition для произвольных типов

**Проблема:** `ClassMemberCompletionContributor` определяет тип
объекта только для `$this->`, `self::`, `static::`. Нет разрешения
типов для произвольных переменных (через TypeSystem).

**Последствия:**
- Completion после `$foo->` не работает, если `$foo` не `$this`.
- Основная функция LSP — интеллектуальное дополнение — ограничена.

**Уровень критичности:** ВЫСОКИЙ

### 4.10. Асимметрия параллелизма контроллеров

**Проблема:** `CompletionController` запускает контрибьюторов параллельно
через `React\Promise\all()`, но `ReferencesController`, `HoverController`,
`DeclarationController` выполняют их последовательно через `foreach`.

```php
// CompletionController — параллельно
$results = await(all($promises));

// ReferencesController — последовательно
foreach ($this->contributors as $contributor) {
    $contributor->contribute($context, $consumer);
}
```

**Последствия:**
- Неконсистентное поведение.
- References/Hover медленнее, чем могли бы быть.

**Уровень критичности:** НИЗКИЙ (пока контрибьюторов мало)

---

## 5. Сравнение с другими LSP/IDE

### 5.1. Intelephense (PHP LSP, TypeScript)

**Архитектура:** Monolithic, TypeScript, closed-source.

| Аспект | Intelephense | php-lsp/language-server |
|--------|-------------|------------------------|
| Язык реализации | TypeScript (Node.js) | PHP (ReactPHP) |
| Индексация | Персистентная (SQLite/файлы), инкрементальная | In-memory, полная, неинкрементальная |
| Разрешение типов | Собственный type inference engine | PHPStan (внешняя зависимость) |
| Cancellation | Да (LSP cancellation protocol) | Нет |
| Производительность | Оптимизирована для 100k+ файлов | Не тестировалась на масштабе |
| Расширяемость | Закрытая (плагины отсутствуют) | Открытая (contributor pattern) |
| Зрелость | Stable (5+ лет) | Pre-release |

**Вывод:** Intelephense значительно впереди по production-readiness, но
уступает в расширяемости благодаря закрытому коду.

### 5.2. Phpactor (PHP LSP, PHP)

**Архитектура:** Extension-based, PHP, open-source.

| Аспект | Phpactor | php-lsp/language-server |
|--------|---------|------------------------|
| Язык | PHP | PHP |
| Архитектура | Extension system (контейнер расширений) | Contributor pattern (Symfony DI) |
| Индексация | Файловая (JSON), инкрементальная | In-memory, неинкрементальная |
| Type inference | Собственный worse-reflection | PHPStan |
| Кеширование | Персистентное (файловая система) | In-memory FIFO |
| Workspace events | `didChangeWatchedFiles` | Не обрабатываются |
| Зрелость | Stable (7+ лет) | Pre-release |

**Вывод:** Phpactor — наиболее близкий по духу проект (PHP на PHP).
Его extension system похожа на contributor pattern, но более зрелая.
Главное преимущество — инкрементальная индексация и файловый кеш.

### 5.3. rust-analyzer (Rust LSP, Rust)

**Архитектура:** Demand-driven (salsa framework), инкрементальные
вычисления.

| Аспект | rust-analyzer | php-lsp/language-server |
|--------|--------------|------------------------|
| Модель вычислений | Demand-driven (lazy, memoized) | Eager (всё вычисляется при запросе) |
| Инкрементальность | Полная (salsa DB, fine-grained) | Отсутствует |
| Cancellation | Да (salsa revision tracking) | Нет |
| Память | Управляемая (GC salsa, LRU) | Неуправляемая (растёт) |
| Параллелизм | Multi-threaded (rayon) | Single-threaded (event loop) |
| Расширяемость | Фиксированная (монолит) | Plugin-based |

**Вывод:** rust-analyzer — золотой стандарт LSP-архитектуры.
Его demand-driven модель недостижима на PHP, но принципы
инкрементальности и cancellation можно адаптировать.

### 5.4. TypeScript Language Server (tsserver)

| Аспект | tsserver | php-lsp/language-server |
|--------|---------|------------------------|
| Индексация | Program object, project references | Flat index |
| Инкрементальность | Incremental parser + checker | Отсутствует |
| Модульность | Монолитная | Plugin-based |
| Диагностики | Полноценный type checker | Только parse errors |

### 5.5. clangd (C/C++ LSP)

| Аспект | clangd | php-lsp/language-server |
|--------|-------|------------------------|
| Индекс | Persistent YAML/binary, background indexer | In-memory |
| Парсинг | Incremental (Clang AST), error-tolerant | Full reparse |
| Threading | Multi-threaded (thread pool) | Single-threaded |
| Cancellation | Полная поддержка | Нет |

---

## 6. Матрица оценки

Оценка по 10-балльной шкале (10 = идеально).

### 6.1. Архитектурные свойства

| Свойство | Оценка | Комментарий |
|----------|:------:|-------------|
| **Модульность** | 8/10 | Отличное разделение на contributor'ы. Но PsiFile/Indexing — монолитные центры. |
| **Расширяемость** | 9/10 | Добавление нового contributor'а = 1 файл. Лучше, чем у большинства LSP. |
| **Тестируемость** | 7/10 | 121 тестовых файлов. Хорошее покрытие модулей, но нет integration/E2E тестов. |
| **Масштабируемость** | 3/10 | In-memory, линейный поиск, отсутствие инкрементальности — не масштабируется. |
| **Производительность** | 4/10 | Полный обход индекса, синхронная индексация, один поток. |
| **Отказоустойчивость** | 6/10 | Timeout на contributor'ы, catch исключений. Но нет cancellation. |
| **Maintainability** | 7/10 | Чистый код, хорошая структура. Но PsiFile — Zone of Pain. |
| **Зрелость** | 3/10 | Pre-release, индексация отключена, debug-код в production. |

### 6.2. Сравнительная матрица с другими LSP

| Свойство | php-lsp | Intelephense | Phpactor | rust-analyzer | clangd |
|----------|:-------:|:------------:|:--------:|:-------------:|:------:|
| Модульность | 8 | 5 | 7 | 6 | 5 |
| Расширяемость | 9 | 3 | 8 | 4 | 3 |
| Инкрементальность | 1 | 8 | 6 | 10 | 9 |
| Персистентный индекс | 1 | 9 | 7 | 10 | 10 |
| Type inference | 4 | 9 | 7 | 10 | 9 |
| Cancellation | 1 | 8 | 5 | 10 | 10 |
| Масштабируемость | 3 | 8 | 6 | 10 | 9 |
| Документация | 8 | 7 | 6 | 10 | 8 |
| **Среднее** | **4.4** | **7.1** | **6.5** | **8.8** | **7.9** |

### 6.3. Диаграмма зон Мартина (Distance from Main Sequence)

```
Abstractness (A)
1.0 ┌────────────────────────────────┐
    │ Zone of               Document │
    │ Uselessness         ·         │
    │                    TypeSystem  │
0.5 │                  ·            │
    │                               │
    │         Main Sequence ────────│
    │        /                      │
    │       /   Indexing ·          │
    │      /                        │
0.0 │ PsiFile ·     Zone of Pain    │
    └────────────────────────────────┘
   0.0    Instability (I)        1.0

PsiFile: I=0.01, A=0.00 → Distance=0.99 (глубоко в Zone of Pain)
Indexing: I=0.23, A=0.08 → Distance=0.69 (в Zone of Pain)
Document: I=0.14, A=0.50 → Distance=0.36 (близко к Main Sequence)
TypeSystem: I=0.75, A=0.20 → Distance=0.05 (на Main Sequence)
```

**PsiFile** — наиболее проблемный модуль: максимальная стабильность
при нулевой абстрактности. Необходимо выделить интерфейсы.

---

## 7. Рекомендации по улучшению

### 7.1. [КРИТИЧНЫЙ] Включить и сделать индексацию инкрементальной

**Текущее состояние:** Индексация полностью отключена (`return` в
`walkWorkspaceFolder`).

**Рекомендация:**

1. Убрать ранний `return`, восстановить вызов `indexer->index()`.
2. Реализовать событие `textDocument/didChange` → частичная
   переиндексация изменённого файла.
3. Добавить `StorageInterface::delete(string $uri)` для удаления
   записей устаревшего файла перед повторной индексацией.
4. Выполнять индексацию в фоне через `React\EventLoop\Loop::addTimer()`.

```php
// Предлагаемый подход:
public function reindexFile(VirtualFileInterface $file): void
{
    $this->storage->deleteByUri((string) $file->uri);
    $this->runIndexers($file);
}
```

### 7.2. [КРИТИЧНЫЙ] Персистентный индекс

**Рекомендация:** Добавить `FileSystemStorage` как альтернативу
`InMemoryStorage`:

- Сериализация в JSON/MessagePack файлы в `.php-lsp/cache/`.
- При старте — загрузка кеша, валидация по mtime файлов.
- Только изменённые файлы переиндексируются.
- `JsonSerializer` уже существует — можно использовать как основу.

**Альтернатива:** SQLite через `ext-pdo_sqlite`:

```
CREATE TABLE symbols (
    key TEXT,
    name TEXT,
    fqn TEXT,
    uri TEXT,
    data BLOB,
    mtime INTEGER
);
CREATE INDEX idx_key_name ON symbols(key, name);
```

Это решит проблемы O(N) поиска (через SQL-индексы) и персистентности
одновременно.

### 7.3. [ВЫСОКИЙ] Выделить интерфейсы для PsiFile

**Проблема:** PsiFile — Zone of Pain (I=0.01, A=0.00).

**Рекомендация:**

```php
// app/Core/Contracts/PsiFile/PsiFileInterface.php
interface PsiFileInterface
{
    public function findAtPosition(Position $position): array;
    public function findLastAtPosition(Position $position): ?Node;
}

// app/Core/Contracts/PsiFile/PsiFileManagerInterface.php
interface PsiFileManagerInterface
{
    public function findPsiFile(EditorInterface $editor, TextDocumentIdentifier $id): ?PsiFileInterface;
}
```

Все зависимости (96 штук) должны зависеть от интерфейсов, а не от
конкретных `InMemoryPsiFileManager` и `PHPPsiFile`.

### 7.4. [ВЫСОКИЙ] Индексированные структуры данных для поиска

**Проблема:** Линейный поиск O(N) при каждом completion/reference запросе.

**Рекомендация:** Добавить вторичные индексы в StorageInterface:

```php
interface StorageInterface
{
    // Существующий
    public function read(string $indexKey): iterable;

    // Новый: поиск по вторичному ключу
    public function findByField(string $indexKey, string $field, mixed $value): iterable;

    // Новый: поиск по префиксу (для completion)
    public function findByPrefix(string $indexKey, string $field, string $prefix): iterable;
}
```

В `InMemoryStorage` — HashMap по полям (`className`, `name`).
В `SQLiteStorage` — SQL-индексы.

### 7.5. [ВЫСОКИЙ] Type-aware completion через TypeSystem

**Проблема:** Completion после `$foo->` не работает для произвольных
переменных.

**Рекомендация:** Интегрировать `TypeResolver` в
`ClassMemberCompletionContributor`:

```php
// Если не $this/self/static — резолвить тип через PHPStan
$typeResult = $this->typeResolver->resolveAtPosition($editor, $doc, $pos);
if ($typeResult !== null) {
    $className = $typeResult->type->describe(VerbosityLevel::typeOnly());
}
```

### 7.6. [СРЕДНИЙ] Cancellation support

**Рекомендация:**

1. Реализовать обработчик `$/cancelRequest`.
2. Передавать `CancellationToken` в контекст.
3. Contributors проверяют токен в циклах:

```php
foreach ($entries as $entry) {
    if ($context->isCancelled()) {
        return;
    }
    // ... обработка
}
```

### 7.7. [СРЕДНИЙ] Разделить InMemoryPsiFileManager

**Рекомендация:** Разбить на:
- `PsiFileCache` — FIFO-кеш AST
- `PsiFileParser` — парсинг (уже есть `PHPPsiFileParser`)
- `DiagnosticPublisher` — отправка диагностик клиенту
- `PsiFileManager` — оркестрация (тонкий фасад)

### 7.8. [СРЕДНИЙ] Конфигурация игнорируемых директорий

**Рекомендация:** Вынести в конфигурационный файл `.php-lsp.json`
или `initializationOptions`:

```json
{
  "exclude": ["vendor/tests", "node_modules", ".git"],
  "stubs": ["php-stubs"]
}
```

### 7.9. [НИЗКИЙ] Унифицировать параллелизм контроллеров

**Рекомендация:** Либо все контроллеры запускают контрибьюторов
параллельно (через общий trait/base), либо все последовательно.
Лучше — параллельно:

```php
// Общий trait для контроллеров
trait ParallelContributorRunner
{
    private function runContributors(array $contributors, $context, $consumer): void
    {
        $promises = array_map(
            fn($c) => timeout(async(fn() => $c->contribute($context, $consumer))(), 1.0),
            $contributors
        );
        await(all($promises));
    }
}
```

### 7.10. [НИЗКИЙ] Удалить закомментированный debug-код

Удалить все `// dump(...)`, `// echo(...)` из production-кода.
Вместо этого использовать логгер с уровнем `debug`.

---

## 8. Приоритеты

### Фаза 1: Базовая функциональность (быстрые победы)

1. Включить индексацию (убрать `return` в InitializeController)
2. Удалить debug-код
3. Вынести список игнорируемых директорий в конфиг

### Фаза 2: Производительность и надёжность

4. Инкрементальная индексация (по изменению файлов)
5. Вторичные индексы в Storage (HashMap по className, name)
6. Выделить PsiFile интерфейсы
7. Cancellation support

### Фаза 3: Масштабируемость

8. Персистентный индекс (SQLite или файловый кеш)
9. Type-aware completion через TypeSystem
10. Унифицировать параллелизм контроллеров

### Фаза 4: Production-readiness

11. Background indexing с progress notifications
12. Workspace change tracking (`didChangeWatchedFiles`)
13. Integration/E2E тесты
14. Memory profiling и лимиты

---

## Заключение

Архитектура php-lsp/language-server демонстрирует **отличный
архитектурный фундамент** — contributor pattern обеспечивает
расширяемость, превосходящую большинство конкурентов (9/10).

Однако **инфраструктурный слой** (индексация, хранилище, кеширование)
находится на раннем этапе развития. Ключевой блокер — отключённая
индексация и отсутствие инкрементальности — делают сервер
нефункциональным в текущем состоянии.

Сильные стороны:
- Элегантный contributor pattern с авто-обнаружением через атрибуты
- Чистое разделение на контроллеры, контракты и модули
- Хорошее тестовое покрытие (121 тестов)
- Качественная документация

Главные зоны роста:
- Инкрементальная индексация (как у Phpactor/Intelephense)
- Персистентный индекс (как у clangd/rust-analyzer)
- Type-aware intelligence (раскрыть потенциал PHPStan)
- Cancellation (стандарт для production LSP)

При реализации фаз 1-3 сервер может выйти на уровень конкурентоспособности
с Phpactor (оценка ~6.5) в течение нескольких месяцев разработки.

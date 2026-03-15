# Анализ миграции с ReactPHP на AMPHP

## Текущее состояние

### Как работает async сейчас

Сервер использует **ReactPHP** через пакет `php-lsp/bridge-server-react`. Текущие паттерны:

| Файл | Паттерн | Истинный параллелизм? |
|------|---------|----------------------|
| `CompletionController` | `React\Promise\all()` + `timeout()` + `async/await` | Нет — кооперативная многозадачность |
| `HoverController` | Последовательный `foreach` | Нет |
| `DeclarationController` | Последовательный `foreach` | Нет |
| `ReferencesController` | Последовательный `foreach` | Нет |
| `SignatureHelpController` | Последовательный `foreach` | Нет |
| `Indexer` | Последовательный `await(async(...))` | Нет |

**Ключевой момент:** даже `CompletionController`, который использует `all($promises)`, выполняет
контрибьютеров **конкурентно**, но **не параллельно**. ReactPHP — это single-threaded event loop.
Если контрибьютер делает CPU-bound работу (парсинг AST, поиск по индексу), он блокирует loop
до тех пор, пока не сделает `delay(0)` (yield). Это **кооперативная многозадачность**, а не
честный параллелизм.

### Что делает `CompletionConsumer::delay(0)`

`CompletionConsumer` вызывает `delay(0)` каждые 100 элементов или каждые 10ms — это yield
обратно в event loop. Это помогает не блокировать обработку других запросов, но сами
контрибьютеры всё равно работают **по очереди** в рамках одного CPU-ядра.

---

## Что предлагает AMPHP

### 1. Кооперативная многозадачность (Fibers) — `amphp/amp`

AMPHP v3 использует PHP Fibers (с PHP 8.1+) вместо промисов. Код пишется как синхронный,
но выполняется конкурентно:

```php
use Amp\Future;
use function Amp\async;

// Запуск контрибьютеров "конкурентно"
$futures = [];
foreach ($contributors as $contributor) {
    $futures[] = async(function () use ($contributor, $context) {
        $consumer = new CompletionConsumer();
        $contributor->contribute($context, $consumer);
        return $consumer->results;
    });
}

$results = Future\await($futures); // Ждём всех
```

**Это НЕ честный параллелизм.** Это то же самое, что сейчас делает ReactPHP, только с
более удобным API (fibers вместо промисов). Код выглядит синхронно, но всё ещё работает в
одном потоке.

### 2. Настоящий параллелизм (Workers) — `amphp/parallel`

**Вот это то, что тебе нужно.** `amphp/parallel` запускает код в **отдельных процессах или
потоках**:

```php
use Amp\Parallel\Worker;
use Amp\Parallel\Worker\ContextWorkerPool;
use function Amp\async;
use function Amp\Future\await;

// Создаём пул воркеров (по количеству CPU ядер)
$pool = new ContextWorkerPool(limit: 8);

$futures = [];
foreach ($contributors as $contributor) {
    $futures[] = async(fn () => $pool->submit(
        new ContributorTask($contributor, $context)
    )->getFuture()->await());
}

$results = await($futures);
```

Где `ContributorTask` — это сериализуемый объект:

```php
use Amp\Parallel\Worker\Task;
use Amp\Sync\Channel;
use Amp\Cancellation;

class ContributorTask implements Task
{
    public function __construct(
        private string $contributorClass,
        private SerializableContext $context,
    ) {}

    public function run(Channel $channel, Cancellation $cancellation): array
    {
        // Этот код выполняется в ОТДЕЛЬНОМ процессе
        $contributor = new ($this->contributorClass)();
        $consumer = new CompletionConsumer();
        $contributor->contribute($this->context, $consumer);
        return $consumer->results;
    }
}
```

**Возможности `amphp/parallel`:**
- Настоящий multi-process параллелизм (каждый воркер = отдельный PHP-процесс)
- Если установлен `ext-parallel` — используются потоки вместо процессов (быстрее)
- Worker pool с конфигурируемым лимитом
- Каналы (Channel) для двунаправленного обмена данными
- Поддержка Cancellation для отмены задач
- Не требует расширений — работает из коробки через child processes

---

## Совместимость с текущей архитектурой

### Проблема: `php-lsp/kernel` привязан к ReactPHP

Монорепозиторий `php-lsp` содержит только один серверный бридж: `bridge-server-react`.
Бриджа для AMPHP (`bridge-server-amp`) **не существует**.

**Но!** Есть решение — **Revolt event loop**:

- Revolt — это **общий event loop**, созданный совместно командами ReactPHP и AMPHP
- AMPHP v3 **уже использует** Revolt как свой event loop
- Пакет `revolt/event-loop-adapter-react` позволяет ReactPHP-коду работать поверх Revolt
- Это значит, что **ReactPHP и AMPHP могут сосуществовать** в одном приложении

### Стратегия миграции

Полная замена ReactPHP на AMPHP **не нужна и не целесообразна** — сервер (`php-lsp/kernel`)
завязан на ReactPHP. Вместо этого предлагается **гибридный подход**:

```
php-lsp/kernel (ReactPHP event loop)
    ↓
Revolt (общий event loop — адаптер)
    ↓
amphp/parallel (Worker Pool для CPU-bound задач)
```

---

## Варианты реализации

### Вариант A: amphp/parallel для CPU-bound контрибьютеров (рекомендуемый)

Оставить ReactPHP для I/O и серверной части. Использовать `amphp/parallel` только для
запуска контрибьютеров в отдельных процессах.

**Плюсы:**
- Минимальные изменения в архитектуре
- Настоящий параллелизм для completion, hover, declaration
- Не ломает совместимость с `php-lsp/kernel`
- Индексирование можно тоже распараллелить

**Минусы:**
- Данные между процессами нужно сериализовать (`Task` должен быть serializable)
- Контрибьютеры не смогут напрямую обращаться к DI-контейнеру в воркере
- Нужно продумать передачу индекса/хранилища в воркеры (shared memory или IPC)

**Сложность сериализации:**
- `CompletionContext`, `DeclarationContext` и т.д. содержат `EditorInterface` — нужно
  либо сериализовать данные документа, либо передавать через Channel
- AST (`PhpParser\Node`) — сериализуемый
- Индекс (`StorageInterface`) — нужен доступ из воркера, варианты:
  - Shared memory (расширение `shmop` или `ext-parallel`)
  - Передать нужный кусок индекса как данные Task
  - Сделать индекс доступным через socket/IPC

### Вариант B: Полная миграция на AMPHP

Заменить `bridge-server-react` на собственный бридж для AMPHP.

**Плюсы:**
- Чистая архитектура, единый стек
- Fibers API удобнее промисов

**Минусы:**
- Нужно написать `bridge-server-amp` (или PR в `php-lsp/php-lsp`)
- Большой объём работы, высокий риск регрессий
- `php-lsp/kernel` может иметь внутренние зависимости от React

### Вариант C: Revolt как общий event loop + amphp/parallel

Установить `revolt/event-loop-adapter-react` чтобы React работал поверх Revolt,
затем использовать `amphp/parallel`.

**Плюсы:**
- Обе экосистемы работают на одном event loop
- Нет конфликтов между React и AMPHP
- Путь к постепенной миграции

**Минусы:**
- Дополнительная зависимость
- Возможны edge-cases совместимости

---

## Рекомендация

**Вариант A (или A+C)** — самый прагматичный:

1. Добавить `amphp/parallel` как зависимость
2. (Опционально) Добавить `revolt/event-loop-adapter-react` для совместимости event loop
3. Создать `WorkerContributorRunner` — сервис, который запускает контрибьютеров через Worker Pool
4. Применить в `CompletionController` и других контроллерах
5. Распараллелить индексирование в `Indexer`

### Примерная архитектура

```
Controller
    ↓
WorkerContributorRunner (amphp/parallel WorkerPool)
    ↓
┌─────────────┬─────────────┬─────────────┐
│  Worker 1   │  Worker 2   │  Worker 3   │  ← отдельные процессы
│ Contributor  │ Contributor  │ Contributor  │
│    A         │    B         │    C         │
└─────────────┴─────────────┴─────────────┘
    ↓              ↓              ↓
    └──────── merge results ──────┘
```

### Что нужно решить перед началом

1. **Сериализация контекста** — как передавать данные документа и позицию курсора в воркер
2. **Доступ к индексу** — как воркеры получают данные индекса (read-only snapshot? IPC?)
3. **Инициализация DI в воркерах** — контрибьютеры зависят от сервисов (Storage, FileManager)
4. **Overhead** — для мелких задач (hover с 2 контрибьютерами) запуск воркера может быть
   дороже последовательного выполнения. Нужен порог: параллелить только если > N контрибьютеров
   или если задача CPU-heavy

---

## Сравнение подходов

| Критерий | ReactPHP (сейчас) | AMPHP Fibers | amphp/parallel |
|----------|-------------------|--------------|----------------|
| Тип конкурентности | Кооперативная | Кооперативная | Настоящий параллелизм |
| CPU-bound задачи | Блокирует loop | Блокирует loop | Не блокирует (отдельный процесс) |
| I/O-bound задачи | Хорошо | Хорошо | Overkill |
| Overhead | Минимальный | Минимальный | Создание процесса + сериализация |
| Сложность | Средняя (промисы) | Низкая (fibers) | Высокая (Task, сериализация) |
| Использование CPU | 1 ядро | 1 ядро | Все ядра |

---

## Вывод

**Да, AMPHP умеет делать честный async** — но не через `amphp/amp` (это те же fibers/coroutines),
а через `amphp/parallel` (отдельные процессы/потоки).

Для LSP-сервера, где контрибьютеры делают CPU-bound работу (парсинг AST, поиск по индексу),
`amphp/parallel` даст **реальный прирост производительности**, позволяя задействовать
несколько ядер CPU одновременно.

Главный челлендж — сериализация данных между главным процессом и воркерами.
Но это решаемая задача, особенно если данные контекста (документ, позиция, индекс)
структурированы как простые DTO.

# APM (Application Performance Monitoring)

The PHP Language Server includes built-in support for distributed tracing via
[OpenTelemetry](https://opentelemetry.io/) and
[SigNoz](https://signoz.io/) as the recommended APM backend.

When enabled, every LSP request produces a trace with spans showing:

- **Request-level timing** — total time for each LSP method
  (`textDocument/completion`, `textDocument/hover`, etc.)
- **Contributor breakdown** — individual span per contributor, so you can see
  which contributor is slow or failing
- **Indexing performance** — workspace indexing traced as a separate span
- **Error recording** — exceptions are automatically captured with stack traces

## Quick Start

### 1. Start SigNoz

```bash
docker compose -f docker/signoz/docker-compose.yaml up -d
```

SigNoz UI will be available at **http://localhost:3301**.

> **Alternative (official SigNoz install):** For a production-grade setup,
> use the [official SigNoz Docker installation](https://signoz.io/docs/install/docker/):
>
> ```bash
> git clone -b main https://github.com/SigNoz/signoz.git
> cd signoz/deploy
> docker compose up -d
> ```

### 2. Install dependencies

The OpenTelemetry SDK and OTLP exporter are included in `composer.json`.
Make sure all dependencies are installed:

```bash
composer install
```

### 3. Start the LSP server with APM enabled

Edit `.env.local` (or `.env`) to set the OTLP endpoint:

```dotenv
OTEL_EXPORTER_OTLP_ENDPOINT=http://localhost:4318
```

Then start the server:

```bash
php ./bin/lsp serve App\\Application --port=5007
```

Alternatively, pass the variable inline:

```bash
OTEL_EXPORTER_OTLP_ENDPOINT=http://localhost:4318 \
  php ./bin/lsp serve App\\Application --port=5007
```

### 4. View traces

Open **http://localhost:3301** → **Traces** tab. You will see traces for
every LSP request handled by the server.

## Configuration

All configuration is done via environment variables defined in `.env`.
To override values locally, create a `.env.local` file (git-ignored):

```bash
cp .env.example .env.local
# Edit .env.local with your settings
```

| Variable | Default | Description |
|---|---|---|
| `OTEL_EXPORTER_OTLP_ENDPOINT` | _(empty — APM disabled)_ | OTLP HTTP endpoint URL (e.g. `http://localhost:4318`) |
| `OTEL_SERVICE_NAME` | `php-lsp` | Service name displayed in the APM dashboard |
| `APP_LOGGER_NAME` | `php-lsp` | Monolog logger channel name |
| `BUGGREGATOR_HOST` | `127.0.0.1:9913` | Buggregator trap server address |

When `OTEL_EXPORTER_OTLP_ENDPOINT` is empty or not set, APM is completely
disabled and a no-op tracer is used — **zero overhead**.

**Environment file precedence** (Symfony Dotenv):
1. Shell environment variables (highest priority — never overwritten)
2. `.env.local` — local overrides (git-ignored)
3. `.env` — committed defaults

## Architecture

### Tracing flow

```
LSP Request
    ↓
Controller.__invoke()
    ├── [Span: textDocument/completion]
    │     ├── [Span: App\Module\Completion\ClassCompletion]
    │     ├── [Span: App\Module\Completion\FunctionCompletion]
    │     └── [Span: App\Module\Completion\KeywordCompletion]
    └── Response
    ↓
BatchSpanProcessor (batches spans)
    ↓
OTLP HTTP Exporter → SigNoz Collector → ClickHouse
    ↓
SigNoz UI (traces, metrics, logs)
```

### Components

| Component | File | Role |
|---|---|---|
| `TracerInterface` | `app/Module/Telemetry/TracerInterface.php` | Application tracing contract |
| `OpenTelemetryTracer` | `app/Module/Telemetry/OpenTelemetryTracer.php` | OTel SDK implementation |
| `NoopTracer` | `app/Module/Telemetry/NoopTracer.php` | No-op (APM disabled) |
| `TracerFactory` | `app/Module/Telemetry/TracerFactory.php` | Creates tracer from env config |
| DI config | `config/services/telemetry.yaml` | Service registration |

### How it works

1. `TracerFactory` reads `OTEL_EXPORTER_OTLP_ENDPOINT` at startup
2. If the endpoint is set and OTel SDK is installed → creates `OpenTelemetryTracer`
3. Otherwise → creates `NoopTracer` (zero overhead)
4. Controllers inject `TracerInterface` and wrap operations in `$this->tracer->trace()`
5. Nested `trace()` calls create parent-child span relationships automatically
6. `BatchSpanProcessor` batches completed spans and exports them periodically

## Using an alternative backend

Since the server uses the standard OpenTelemetry OTLP protocol, you can use
any OTLP-compatible backend instead of SigNoz:

### Jaeger (lightweight, traces only)

```bash
# Start Jaeger with OTLP support
docker run -d --name jaeger \
  -p 4317:4317 \
  -p 4318:4318 \
  -p 16686:16686 \
  jaegertracing/jaeger:2

# Start LSP server
OTEL_EXPORTER_OTLP_ENDPOINT=http://localhost:4318 \
  php ./bin/lsp serve App\\Application --port=5007
```

Jaeger UI: **http://localhost:16686**

### Grafana Tempo

```bash
OTEL_EXPORTER_OTLP_ENDPOINT=http://localhost:4318 \
  php ./bin/lsp serve App\\Application --port=5007
```

See [Grafana Tempo documentation](https://grafana.com/docs/tempo/latest/)
for setup instructions.

## Adding traces to custom code

Inject `TracerInterface` into any service and use the `trace()` method:

```php
use App\Module\Telemetry\TracerInterface;

final class MyService
{
    public function __construct(
        private readonly TracerInterface $tracer,
    ) {}

    public function doWork(): mixed
    {
        return $this->tracer->trace('my-operation', function () {
            // Your code here — automatically wrapped in a span.
            // Nested trace() calls create child spans.
            return $this->tracer->trace('sub-operation', function () {
                return $this->expensiveCalculation();
            });
        }, ['custom.attribute' => 'value']);
    }
}
```

## Troubleshooting

### No traces appearing in SigNoz

1. Verify SigNoz is running: `docker compose -f docker/signoz/docker-compose.yaml ps`
2. Check the OTLP endpoint is reachable: `curl http://localhost:4318/v1/traces`
3. Verify the env var is set: `echo $OTEL_EXPORTER_OTLP_ENDPOINT`
4. Check LSP server logs for export errors in `var/prod.log`

### High memory usage

The `BatchSpanProcessor` accumulates spans in memory before exporting.
For high-throughput scenarios, you can tune the batch size via OTel SDK
environment variables:

```bash
OTEL_BSP_MAX_EXPORT_BATCH_SIZE=512
OTEL_BSP_SCHEDULE_DELAY=5000
```

### Disabling APM

Simply unset `OTEL_EXPORTER_OTLP_ENDPOINT` or set it to an empty string.
The `NoopTracer` will be used with zero performance impact.

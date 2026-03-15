<?php

declare(strict_types=1);

namespace App\Module\Indexing\Storage;

use Psr\Log\LoggerInterface;

/**
 * Client for the Go-based indexer RPC server.
 *
 * For MVP benchmarking, this client uses a "one-shot" mode
 * where the Go binary is invoked as a subprocess and results are read from stdout.
 */
final class GoIndexerClient
{
    /** @var array{classes: list<array<string, mixed>>, functions: list<array<string, mixed>>, file_count: int}|null */
    private ?array $cachedResult = null;

    public function __construct(
        private readonly string $binaryPath,
        private readonly ?LoggerInterface $logger = null,
    ) {}

    /**
     * Index a workspace directory using the Go indexer binary (one-shot mode).
     *
     * @return array{classes: list<array<string, mixed>>, functions: list<array<string, mixed>>, file_count: int}
     */
    public function indexWorkspace(string $path, int $concurrency = 8): array
    {
        $startTime = microtime(true);
        $result = $this->executeIndexer($path, $concurrency);

        $elapsed = microtime(true) - $startTime;
        $this->logger?->info(sprintf(
            'Go indexer completed: %d files, %d classes, %d functions in %.3fs',
            $result['file_count'],
            count($result['classes']),
            count($result['functions']),
            $elapsed,
        ));

        $this->cachedResult = $result;

        return $result;
    }

    /**
     * Search indexed classes by name/FQN.
     *
     * @return list<array<string, mixed>>
     */
    public function searchClasses(string $query = ''): array
    {
        return $this->filterCached('classes', $query);
    }

    /**
     * Search indexed functions by name/FQN.
     *
     * @return list<array<string, mixed>>
     */
    public function searchFunctions(string $query = ''): array
    {
        return $this->filterCached('functions', $query);
    }

    /**
     * Get the number of indexed files.
     */
    public function getFileCount(): int
    {
        return $this->cachedResult['file_count'] ?? 0;
    }

    /**
     * Check if the Go binary exists and is executable.
     */
    public function isAvailable(): bool
    {
        return file_exists($this->binaryPath) && is_executable($this->binaryPath);
    }

    /**
     * Clear cached results.
     */
    public function clear(): void
    {
        $this->cachedResult = null;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function filterCached(string $key, string $query): array
    {
        if ($this->cachedResult === null) {
            return [];
        }

        /** @var list<array<string, mixed>> $items */
        $items = $this->cachedResult[$key] ?? [];
        if ($query === '') {
            return $items;
        }

        $queryLower = mb_strtolower($query);

        return array_values(array_filter(
            $items,
            /** @param array<string, mixed> $item */
            static fn(array $item): bool => (
                str_contains(mb_strtolower((string) ($item['fqn'] ?? '')), $queryLower)
                || str_contains(mb_strtolower((string) ($item['name'] ?? '')), $queryLower)
            ),
        ));
    }

    /**
     * @return array{classes: list<array<string, mixed>>, functions: list<array<string, mixed>>, file_count: int}
     */
    private function executeIndexer(string $path, int $concurrency): array
    {
        /** @var array{classes: list<array<string, mixed>>, functions: list<array<string, mixed>>, file_count: int} $empty */
        $empty = ['classes' => [], 'functions' => [], 'file_count' => 0];

        $command = sprintf(
            '%s -mode=index -path=%s -concurrency=%d',
            escapeshellarg($this->binaryPath),
            escapeshellarg($path),
            $concurrency,
        );

        $output = shell_exec($command);
        if ($output === null || $output === false || $output === '') {
            $this->logger?->error('Go indexer returned no output', ['command' => $command]);

            return $empty;
        }

        /** @var array{classes: list<array<string, mixed>>, functions: list<array<string, mixed>>, file_count: int}|null $result */
        $result = json_decode($output, associative: true);
        if (!is_array($result)) {
            $this->logger?->error('Go indexer returned invalid JSON');

            return $empty;
        }

        return $result;
    }
}

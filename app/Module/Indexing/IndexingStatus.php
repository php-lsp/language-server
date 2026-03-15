<?php

declare(strict_types=1);

namespace App\Module\Indexing;

/**
 * Tracks the current state of the indexing process.
 * Shared singleton — updated by Indexer, read by DebugHttpServer.
 */
final class IndexingStatus
{
    private bool $indexing = false;
    private int $filesIndexed = 0;
    private ?float $lastIndexedAt = null;
    private ?float $lastDuration = null;
    private ?float $startedAt = null;

    public function start(): void
    {
        $this->indexing = true;
        $this->filesIndexed = 0;
        $this->startedAt = microtime(true);
    }

    public function fileIndexed(): void
    {
        $this->filesIndexed++;
    }

    public function finish(): void
    {
        $this->indexing = false;
        $this->lastIndexedAt = microtime(true);
        $this->lastDuration = $this->startedAt !== null ? $this->lastIndexedAt - $this->startedAt : null;
        $this->startedAt = null;
    }

    /**
     * @return array{indexing: bool, filesIndexed: int, lastIndexedAt: ?float, lastDuration: ?float}
     */
    public function toArray(): array
    {
        return [
            'indexing' => $this->indexing,
            'filesIndexed' => $this->filesIndexed,
            'lastIndexedAt' => $this->lastIndexedAt,
            'lastDuration' => $this->lastDuration !== null ? round($this->lastDuration, 3) : null,
        ];
    }
}

<?php

declare(strict_types=1);

namespace App\Module\Parallel;

use Amp\Parallel\Worker\ContextWorkerPool;
use Amp\Parallel\Worker\WorkerPool;

final class WorkerPoolFactory
{
    private ?WorkerPool $pool = null;

    public function __construct(
        private readonly int $workerLimit = 4,
    ) {}

    public function getPool(): WorkerPool
    {
        return $this->pool ??= new ContextWorkerPool($this->workerLimit);
    }

    public function shutdown(): void
    {
        $this->pool?->shutdown();
        $this->pool = null;
    }
}

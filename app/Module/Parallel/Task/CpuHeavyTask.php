<?php

declare(strict_types=1);

namespace App\Module\Parallel\Task;

use Amp\Cancellation;
use Amp\Parallel\Worker\Task;
use Amp\Sync\Channel;
use Override;

/**
 * Simulates a CPU-heavy contributor task with small data transfer.
 * Represents scenarios like AST parsing where source code (small) is sent
 * but computation (parsing + analysis) is heavy.
 *
 * @implements Task<list<array{label: string, kind: int}>, mixed, mixed>
 */
final class CpuHeavyTask implements Task
{
    public function __construct(
        private readonly string $sourceCode,
        private readonly int $workIterations,
    ) {}

    /**
     * @return list<array{label: string, kind: int}>
     */
    #[Override]
    public function run(Channel $channel, Cancellation $cancellation): array
    {
        $results = [];
        $matches = [];

        // Simulate CPU-heavy work: regex matching, hashing, string manipulation
        for ($i = 0; $i < $this->workIterations; ++$i) {
            preg_match_all('/\b\w+\b/', $this->sourceCode, $matches);
            $hash = md5($this->sourceCode . $i);
            $results[] = [
                'label' => substr($hash, offset: 0, length: 8),
                'kind' => $i % 10,
            ];
        }

        return $results;
    }
}

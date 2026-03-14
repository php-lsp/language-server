<?php

declare(strict_types=1);

namespace App\Module\Parallel\Task;

use Amp\Cancellation;
use Amp\Parallel\Worker\Task;
use Amp\Sync\Channel;

/**
 * Worker task that performs index search in a separate process.
 * Receives serialized index data and a query string,
 * returns matching results as plain arrays.
 *
 * @implements Task<array<int, array{label: string, kind: int, detail: string}>, mixed, mixed>
 */
final class IndexSearchTask implements Task
{
    /**
     * @param list<string> $index
     */
    public function __construct(
        private readonly array $index,
        private readonly string $query,
    ) {}

    public function run(Channel $channel, Cancellation $cancellation): array
    {
        $results = [];

        foreach ($this->index as $entry) {
            if (stripos($entry, $this->query) === false) {
                continue;
            }

            $results[] = [
                'label' => $entry,
                'kind' => 6,
                'detail' => '[class]',
            ];
        }

        return $results;
    }
}

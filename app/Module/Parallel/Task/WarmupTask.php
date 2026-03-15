<?php

declare(strict_types=1);

namespace App\Module\Parallel\Task;

use Amp\Cancellation;
use Amp\Parallel\Worker\Task;
use Amp\Sync\Channel;

/**
 * Simple task to warm up worker processes.
 *
 * @implements Task<bool, mixed, mixed>
 */
final class WarmupTask implements Task
{
    public function run(Channel $channel, Cancellation $cancellation): bool
    {
        return true;
    }
}

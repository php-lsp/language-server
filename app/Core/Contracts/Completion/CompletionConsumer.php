<?php

declare(strict_types=1);

namespace App\Core\Contracts\Completion;

use Lsp\Protocol\Type\CompletionItem;

use function React\Async\delay;

class CompletionConsumer
{
    private int $itemCount = 0;
    private float $lastYield;
    private int $resultCount = 0;

    /**
     * Number of items between yield checks.
     */
    private const int YIELD_EVERY = 100;

    /**
     * Maximum time between yields (milliseconds).
     */
    private const int YIELD_INTERVAL_MS = 10;

    public function __construct(
        /**
         * @var list<CompletionItem>
         */
        public array $results = [],

        /**
         * Maximum number of results (null = unlimited).
         */
        public ?int $limit = null,
    ) {
        $this->lastYield = microtime(true);
        $this->resultCount = count($results);
    }

    public function __invoke(CompletionItem ...$items): void
    {
        foreach ($items as $item) {
            if ($this->limit !== null && $this->resultCount >= $this->limit) {
                return;
            }

            $this->results[] = $item;
            ++$this->resultCount;
            ++$this->itemCount;

            if ($this->shouldYield()) {
                delay(0);
                $this->lastYield = microtime(true);
            }
        }
    }

    private function shouldYield(): bool
    {
        if (($this->itemCount % self::YIELD_EVERY) !== 0) {
            return false;
        }

        $now = microtime(true);
        $elapsedMs = ($now - $this->lastYield) * 1000;

        return $elapsedMs > self::YIELD_INTERVAL_MS;
    }
}

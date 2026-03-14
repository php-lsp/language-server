<?php

declare(strict_types=1);

namespace App\Core\Contracts\Completion;

use Lsp\Protocol\Type\CompletionItem;

use function React\Async\delay;

class CompletionConsumer
{
    private int $itemCount = 0;
    private float $lastYield;

    /**
     * Количество элементов между yield
     */
    private const YIELD_EVERY = 100;

    /**
     * Максимальное время между yield (в миллисекундах)
     */
    private const YIELD_INTERVAL_MS = 10;

    public function __construct(
        /**
         * @var list<CompletionItem>
         */
        public array $results = [],

        /**
         * Максимальное количество результатов (null = без лимита)
         */
        public ?int $limit = null,
    ) {
        $this->lastYield = microtime(true);
    }

    public function __invoke(CompletionItem ...$items): void
    {
        //        array_push($this->results, ...$items);
        //        return;
        foreach ($items as $item) {
            // Проверяем лимит
            if ($this->limit !== null && count($this->results) >= $this->limit) {
                return;
            }

            $this->results[] = $item;
            ++$this->itemCount;

            if ($this->shouldYield()) {
                delay(0);
                $this->lastYield = microtime(true);
            }
        }
    }

    /**
     * Проверить нужно ли отдать управление
     */
    private function shouldYield(): bool
    {
        if (($this->itemCount % self::YIELD_EVERY) === 0) {
            return true;
        }

        $now = microtime(true);
        $elapsedMs = ($now - $this->lastYield) * 1000;

        return $elapsedMs > self::YIELD_INTERVAL_MS;
    }
}

<?php

declare(strict_types=1);

namespace App\Controller\Debug;

use App\Module\Indexing\Storage\DebugStorageInterface;
use Lsp\Kernel\Attribute\AsController;
use Lsp\Router\Attribute\Route;

/**
 * Searches entries by key glob pattern within an index, or across all indexes.
 *
 * Request:  { "method": "debug/index/search", "params": { "pattern": "App\\Controller\\*", "index": "php.classes.fqn" } }
 * Response: { "php.classes.fqn": [{ "key": "App\\Controller\\Foo", "value": ..., "uri": ... }, ...] }
 */
#[AsController, Route('debug/index/search')]
final class IndexSearchController
{
    public function __construct(
        private readonly DebugStorageInterface $storage,
    ) {}

    /**
     * @param object{pattern: string, index?: string, limit?: int} $params
     * @return array<string, list<array{key: string|int, value: mixed, uri: string}>>
     */
    public function __invoke(object $params): array
    {
        $pattern = $params->pattern;
        $limit = isset($params->limit) ? \max(1, $params->limit) : 100;

        $indexKeys = isset($params->index) && $params->index !== '' ? [$params->index] : $this->storage->getIndexKeys();

        $results = [];

        foreach ($indexKeys as $indexKey) {
            $matches = [];
            $count = 0;

            foreach ($this->storage->search($indexKey, $pattern) as $entry) {
                if ($count >= $limit) {
                    break;
                }

                $matches[] = [
                    'key' => $entry->key,
                    'value' => $this->summarizeValue($entry->value),
                    'uri' => $entry->uri,
                ];
                $count++;
            }

            if ($matches !== []) {
                $results[$indexKey] = $matches;
            }
        }

        return $results;
    }

    private function summarizeValue(mixed $value): mixed
    {
        if (\is_scalar($value) || $value === null) {
            return $value;
        }

        if (\is_array($value)) {
            return '(array[' . \count($value) . '])';
        }

        if (\is_object($value)) {
            return '(' . $value::class . ')';
        }

        return '(' . \get_debug_type($value) . ')';
    }
}

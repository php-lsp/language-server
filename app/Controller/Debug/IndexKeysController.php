<?php

declare(strict_types=1);

namespace App\Controller\Debug;

use App\Module\Indexing\Storage\DebugStorageInterface;
use Lsp\Kernel\Attribute\AsController;
use Lsp\Router\Attribute\Route;

/**
 * Returns keys of a specific index with optional pattern filtering and pagination.
 *
 * Request:  { "method": "debug/index/keys", "params": { "index": "php.classes.fqn", "pattern": "App\\*", "limit": 50, "offset": 0 } }
 * Response: { "index": "php.classes.fqn", "total": 120, "keys": ["App\\Foo", ...], "limit": 50, "offset": 0 }
 */
#[AsController, Route('debug/index/keys')]
final class IndexKeysController
{
    public function __construct(
        private readonly DebugStorageInterface $storage,
    ) {}

    /**
     * @param object{index: string, pattern?: string, limit?: int, offset?: int} $params
     * @return array<string, mixed>
     */
    public function __invoke(object $params): array
    {
        $indexKeys = $this->storage->getIndexKeys();

        if (!\in_array($params->index, $indexKeys, true)) {
            return [
                'error' => "Index '{$params->index}' not found",
                'available' => $indexKeys,
            ];
        }

        $keys = [];

        foreach ($this->storage->read($params->index) as $entry) {
            $key = $entry->key;

            if (isset($params->pattern) && $params->pattern !== '') {
                if (!\fnmatch($params->pattern, (string) $key, \FNM_CASEFOLD | \FNM_NOESCAPE)) {
                    continue;
                }
            }

            $keys[] = $key;
        }

        $total = \count($keys);
        $offset = isset($params->offset) ? \max(0, $params->offset) : 0;
        $limit = isset($params->limit) ? \max(1, $params->limit) : 100;

        $keys = \array_slice($keys, $offset, $limit);

        return [
            'index' => $params->index,
            'total' => $total,
            'offset' => $offset,
            'limit' => $limit,
            'keys' => $keys,
        ];
    }
}

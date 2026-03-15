<?php

declare(strict_types=1);

namespace App\Controller\Debug;

use App\Index\IndexRegistryInterface;
use Lsp\Kernel\Attribute\AsController;
use Lsp\Router\Attribute\Route;

/**
 * Returns keys of a specific index with optional glob filtering and pagination.
 *
 * Request:  { "method": "debug/index/keys", "params": { "index": "classes", "pattern": "App\\*", "limit": 50, "offset": 0 } }
 * Response: { "index": "classes", "total": 120, "keys": ["App\\Foo", ...], "limit": 50, "offset": 0 }
 */
#[AsController, Route('debug/index/keys')]
final class IndexKeysController
{
    public function __construct(
        private readonly IndexRegistryInterface $registry,
    ) {}

    /**
     * @param object{index: non-empty-string, pattern?: string, limit?: int, offset?: int} $params
     * @return array<string, mixed>
     */
    public function __invoke(object $params): array
    {
        $index = $this->registry->get($params->index);

        if ($index === null) {
            return ['error' => "Index '{$params->index}' not found", 'available' => $this->registry->names()];
        }

        $keys = $index->keys();

        // Filter by pattern if provided
        if (isset($params->pattern) && $params->pattern !== '') {
            $keys = \array_values(\array_filter(
                $keys,
                static fn(int|string $key): bool => \fnmatch($params->pattern, (string) $key, \FNM_CASEFOLD),
            ));
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

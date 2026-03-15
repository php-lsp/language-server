<?php

declare(strict_types=1);

namespace App\Controller\Debug;

use App\Index\IndexRegistryInterface;
use Lsp\Kernel\Attribute\AsController;
use Lsp\Router\Attribute\Route;

/**
 * Searches across indexes using a glob pattern on keys.
 *
 * Request:  { "method": "debug/index/search", "params": { "pattern": "App\\Controller\\*" } }
 * Response: { "classes": { "App\\Controller\\Foo": { ... } }, "symbols": { ... } }
 *
 * Optional: restrict search to a single index with "index" param.
 */
#[AsController, Route('debug/index/search')]
final class IndexSearchController
{
    public function __construct(
        private readonly IndexRegistryInterface $registry,
    ) {}

    /**
     * @param object{pattern: string, index?: string} $params
     * @return array<string, mixed>
     */
    public function __invoke(object $params): array
    {
        $pattern = $params->pattern;

        // If a specific index is requested, search only within it
        if (isset($params->index) && $params->index !== '') {
            $index = $this->registry->get($params->index);

            if ($index === null) {
                return ['error' => "Index '{$params->index}' not found", 'available' => $this->registry->names()];
            }

            $matches = [];

            foreach ($index->keys() as $key) {
                if (\fnmatch($pattern, (string) $key, \FNM_CASEFOLD)) {
                    $inspected = $index->inspect($key);
                    if ($inspected !== null) {
                        $matches[$key] = $inspected;
                    }
                }
            }

            return [$index->getName() => $matches];
        }

        return $this->registry->search($pattern);
    }
}

<?php

declare(strict_types=1);

namespace App\Controller\Debug;

use App\Index\IndexRegistryInterface;
use Lsp\Kernel\Attribute\AsController;
use Lsp\Router\Attribute\Route;

/**
 * Inspects a single entry by index name and key.
 *
 * Request:  { "method": "debug/index/get", "params": { "index": "classes", "key": "App\\Foo" } }
 * Response: { "key": "App\\Foo", "type": "object", "class": "...", "value": { ... } }
 */
#[AsController, Route('debug/index/get')]
final class IndexGetController
{
    public function __construct(
        private readonly IndexRegistryInterface $registry,
    ) {}

    /**
     * @param object{index: non-empty-string, key: string} $params
     * @return array<string, mixed>
     */
    public function __invoke(object $params): array
    {
        $index = $this->registry->get($params->index);

        if ($index === null) {
            return ['error' => "Index '{$params->index}' not found", 'available' => $this->registry->names()];
        }

        $result = $index->inspect($params->key);

        if ($result === null) {
            return ['error' => "Key '{$params->key}' not found in index '{$params->index}'"];
        }

        return $result;
    }
}

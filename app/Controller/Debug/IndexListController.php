<?php

declare(strict_types=1);

namespace App\Controller\Debug;

use App\Index\IndexRegistryInterface;
use Lsp\Kernel\Attribute\AsController;
use Lsp\Router\Attribute\Route;

/**
 * Lists all registered indexes with their summaries.
 *
 * Request:  { "method": "debug/index/list" }
 * Response: [{ "name": "classes", "count": 42, "keys": [...] }, ...]
 */
#[AsController, Route('debug/index/list')]
final class IndexListController
{
    public function __construct(
        private readonly IndexRegistryInterface $registry,
    ) {}

    /**
     * @return list<array{name: non-empty-string, count: int<0, max>, keys: list<array-key>}>
     */
    public function __invoke(): array
    {
        return $this->registry->summaries();
    }
}

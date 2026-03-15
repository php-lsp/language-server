<?php

declare(strict_types=1);

namespace App\Controller\Debug;

use App\Module\Indexing\Storage\DebugStorageInterface;
use Lsp\Kernel\Attribute\AsController;
use Lsp\Router\Attribute\Route;

/**
 * Lists all index keys and their entry counts.
 *
 * Request:  { "method": "debug/index/list" }
 * Response: { "php.classes.fqn": { "key": "php.classes.fqn", "count": 42 }, ... }
 */
#[AsController, Route('debug/index/list')]
final class IndexListController
{
    public function __construct(
        private readonly DebugStorageInterface $storage,
    ) {}

    /**
     * @return array<string, array{key: string, count: int}>
     */
    public function __invoke(): array
    {
        return $this->storage->stats();
    }
}

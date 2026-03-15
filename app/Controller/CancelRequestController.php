<?php

declare(strict_types=1);

namespace App\Controller;

use App\Core\Cancellation\CancellationTokenRegistry;
use Lsp\Kernel\Attribute\AsController;
use Lsp\Router\Attribute\Route;

#[AsController, Route('$/cancelRequest')]
final class CancelRequestController
{
    public function __construct(
        private readonly CancellationTokenRegistry $registry,
    ) {}

    public function __invoke(object $params): void
    {
        $id = $params->id ?? null;
        if ($id !== null) {
            $this->registry->cancel($id);
        }
    }
}

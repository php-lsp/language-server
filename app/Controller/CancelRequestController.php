<?php

declare(strict_types=1);

namespace App\Controller;

use App\Core\Cancellation\CancellationTokenRegistry;
use Lsp\Kernel\Attribute\AsController;
use Lsp\Protocol\Type\CancelParams;
use Lsp\Router\Attribute\Route;

#[AsController, Route('$/cancelRequest')]
final class CancelRequestController
{
    public function __construct(
        private readonly CancellationTokenRegistry $registry,
    ) {}

    public function __invoke(CancelParams $params): void
    {
        $this->registry->cancel($params->id);
    }
}

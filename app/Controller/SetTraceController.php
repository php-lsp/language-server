<?php

declare(strict_types=1);

namespace App\Controller;

use Lsp\Extension\DocumentManager\Editor\EditorInterface;
use Lsp\Kernel\Attribute\AsController;
use Lsp\Protocol\Type\SetTraceParams;
use Lsp\Router\Attribute\Route;
use Psr\Log\LoggerInterface;

#[AsController, Route('$/setTrace')]
final class SetTraceController
{
    public function __construct(
        private LoggerInterface $logger,
    ) {}

    public function __invoke(EditorInterface $editor, SetTraceParams $params): void
    {
        $value = $params->value;

        $this->logger->info('Trace value set to: ' . $value->name);
    }
}

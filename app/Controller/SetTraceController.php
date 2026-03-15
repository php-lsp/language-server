<?php

declare(strict_types=1);

namespace App\Controller;

use Lsp\Kernel\Attribute\AsController;
use Lsp\Protocol\Type\SetTraceParams;
use Lsp\Protocol\Type\TraceValue;
use Lsp\Router\Attribute\Route;
use Monolog\Handler\HandlerInterface;
use Monolog\Level;
use Monolog\Logger;
use Psr\Log\LoggerInterface;

#[AsController, Route('$/setTrace')]
final class SetTraceController
{
    public function __construct(
        private LoggerInterface $logger,
    ) {}

    public function __invoke(SetTraceParams $params): void
    {
        $value = $params->value;
        $level = match ($value) {
            TraceValue::Off => Level::Warning,
            TraceValue::Messages => Level::Info,
            TraceValue::Verbose => Level::Debug,
        };

        if ($this->logger instanceof Logger) {
            /** @var HandlerInterface $handler */
            foreach ($this->logger->getHandlers() as $handler) {
                if (!\method_exists($handler, 'setLevel')) {
                    continue;
                }

                $handler->setLevel($level);
            }
        }

        $this->logger->info('Trace level set to {trace} (log level: {level})', [
            'trace' => $value->name,
            'level' => $level->name,
        ]);
    }
}

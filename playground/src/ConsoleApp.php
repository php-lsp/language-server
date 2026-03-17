<?php

declare(strict_types=1);

namespace Playground;

/**
 * Console application implementation for testing implementation and call hierarchy features.
 */
class ConsoleApp implements AppInterface
{
    public function run(): int
    {
        $greeter = new Greeter('Console');
        $message = $greeter->greet('Starting');
        $this->log($message);

        return 0;
    }

    public function getAppName(): string
    {
        return 'console';
    }

    private function log(string $message): void
    {
        // no-op for testing
    }
}

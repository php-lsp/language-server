<?php

declare(strict_types=1);

namespace Playground;

/**
 * Application interface for testing interface-related LSP features.
 */
interface AppInterface
{
    /**
     * Run the application.
     *
     * @return int Exit code
     */
    public function run(): int;

    /**
     * Get the application name.
     */
    public function getAppName(): string;
}

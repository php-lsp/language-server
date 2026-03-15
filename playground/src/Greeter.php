<?php

declare(strict_types=1);

namespace Playground;

/**
 * A simple greeter class for testing hover documentation and completion.
 */
class Greeter
{
    public function __construct(
        private readonly string $name,
    ) {}

    /**
     * Returns a greeting message.
     *
     * @param string $prefix The prefix for the greeting
     * @return string The formatted greeting
     */
    public function greet(string $prefix = 'Hello'): string
    {
        return \sprintf('%s, %s!', $prefix, $this->name);
    }

    /**
     * Returns the name.
     */
    public function getName(): string
    {
        return $this->name;
    }
}

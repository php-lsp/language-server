<?php

declare(strict_types=1);

namespace Playground;

/**
 * User entity for testing hover and declaration features.
 */
class User
{
    public function __construct(
        private readonly string $name,
        private readonly string $email,
    ) {}

    public function getName(): string
    {
        return $this->name;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function __toString(): string
    {
        return \sprintf('%s <%s>', $this->name, $this->email);
    }
}

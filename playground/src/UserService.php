<?php

declare(strict_types=1);

namespace Playground;

use Psr\Log\LoggerInterface;

/**
 * User service for testing declaration, references, and completion with dependencies.
 */
class UserService
{
    /** @var array<string, User> */
    private array $users = [];

    public function __construct(
        private readonly LoggerInterface $logger,
    ) {}

    /**
     * Create a new user.
     *
     * @param string $name The user's name
     * @param string $email The user's email address
     * @return User The created user
     */
    public function createUser(string $name, string $email): User
    {
        $user = new User($name, $email);
        $this->users[$email] = $user;
        $this->logger->info('User created: ' . $name);
        return $user;
    }

    /**
     * Find a user by email.
     *
     * @param string $email The email address to search for
     * @return User|null The found user or null
     */
    public function findByEmail(string $email): ?User
    {
        return $this->users[$email] ?? null;
    }

    /**
     * Get all users.
     *
     * @return array<string, User>
     */
    public function getAllUsers(): array
    {
        return $this->users;
    }
}

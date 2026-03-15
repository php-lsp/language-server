<?php

declare(strict_types=1);

namespace App\Module\Notification;

use Lsp\Contracts\Server\ConnectionInterface;

final class ActiveConnectionProvider
{
    private ?ConnectionInterface $connection = null;

    public function set(ConnectionInterface $connection): void
    {
        $this->connection = $connection;
    }

    public function get(): ?ConnectionInterface
    {
        return $this->connection;
    }
}

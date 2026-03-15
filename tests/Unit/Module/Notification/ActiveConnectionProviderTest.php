<?php

declare(strict_types=1);

namespace App\Tests\Unit\Module\Notification;

use App\Module\Notification\ActiveConnectionProvider;
use App\Tests\TestCase;
use Lsp\Contracts\Server\ConnectionInterface;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\TestDox;

#[Group('unit')]
final class ActiveConnectionProviderTest extends TestCase
{
    #[TestDox('returns null when no connection is set')]
    public function testReturnsNullWhenNoConnection(): void
    {
        $provider = new ActiveConnectionProvider();

        $this->assertNull($provider->get());
    }

    #[TestDox('returns connection after it is set')]
    public function testReturnsConnectionAfterSet(): void
    {
        $provider = new ActiveConnectionProvider();
        $connection = $this->createMock(ConnectionInterface::class);

        $provider->set($connection);

        $this->assertSame($connection, $provider->get());
    }

    #[TestDox('replaces previous connection')]
    public function testReplacesPreviousConnection(): void
    {
        $provider = new ActiveConnectionProvider();
        $first = $this->createMock(ConnectionInterface::class);
        $second = $this->createMock(ConnectionInterface::class);

        $provider->set($first);
        $provider->set($second);

        $this->assertSame($second, $provider->get());
    }
}

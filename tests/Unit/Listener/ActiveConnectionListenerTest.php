<?php

declare(strict_types=1);

namespace App\Tests\Unit\Listener;

use App\Listener\ActiveConnectionListener;
use App\Module\Notification\ActiveConnectionProvider;
use App\Tests\TestCase;
use Lsp\Contracts\Rpc\Message\MessageInterface;
use Lsp\Contracts\Server\ConnectionInterface;
use Lsp\Server\Event\Message\MessageReceived;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\TestDox;

#[Group('unit')]
final class ActiveConnectionListenerTest extends TestCase
{
    #[TestDox('sets active connection from message event')]
    public function testSetsActiveConnection(): void
    {
        $provider = new ActiveConnectionProvider();
        $listener = new ActiveConnectionListener($provider);

        $message = $this->createMock(MessageInterface::class);
        $connection = $this->createMock(ConnectionInterface::class);

        $event = new MessageReceived($message, $connection);
        $listener($event);

        $this->assertSame($connection, $provider->get());
    }

    #[TestDox('updates connection on subsequent messages')]
    public function testUpdatesConnectionOnSubsequentMessages(): void
    {
        $provider = new ActiveConnectionProvider();
        $listener = new ActiveConnectionListener($provider);

        $message1 = $this->createMock(MessageInterface::class);
        $connection1 = $this->createMock(ConnectionInterface::class);
        $listener(new MessageReceived($message1, $connection1));

        $message2 = $this->createMock(MessageInterface::class);
        $connection2 = $this->createMock(ConnectionInterface::class);
        $listener(new MessageReceived($message2, $connection2));

        $this->assertSame($connection2, $provider->get());
    }
}

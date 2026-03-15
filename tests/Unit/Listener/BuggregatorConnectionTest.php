<?php

declare(strict_types=1);

namespace App\Tests\Unit\Listener;

use App\Tests\TestCase;
use Monolog\Formatter\JsonFormatter;
use Monolog\Handler\SocketHandler;
use Monolog\Handler\WhatFailureGroupHandler;
use Monolog\Level;
use Monolog\Logger;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\TestDox;

#[Group('unit')]
final class BuggregatorConnectionTest extends TestCase
{
    #[TestDox('logger does not crash when Buggregator address is unreachable')]
    public function testLoggerDoesNotCrashWithInvalidBuggregatorAddress(): void
    {
        $socket = new SocketHandler('tcp://invalid-host-that-does-not-exist:99999', Level::Debug);
        $socket->setFormatter(new JsonFormatter(appendNewline: true));

        $handler = new WhatFailureGroupHandler([$socket]);
        $logger = new Logger('test', [$handler]);

        $logger->info('test message', ['key' => 'value']);
        $logger->error('another test message');

        $this->assertTrue(true, 'Logger should not throw on unreachable Buggregator');
    }
}

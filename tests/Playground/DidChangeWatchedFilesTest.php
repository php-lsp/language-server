<?php

declare(strict_types=1);

namespace App\Tests\Playground;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\TestDox;

#[Group('e2e')]
#[TestDox('workspace/didChangeWatchedFiles')]
final class DidChangeWatchedFilesTest extends PlaygroundTestCase
{
    #[TestDox('Server handles didChangeWatchedFiles notification without crashing')]
    public function testHandlesNotificationWithoutCrashing(): void
    {
        $uri = self::playgroundFileUri('src/Greeter.php');

        self::$client->notify('workspace/didChangeWatchedFiles', [
            'changes' => [
                ['uri' => $uri, 'type' => 2], // Changed
            ],
        ]);

        // Small delay to let the server process the notification
        \usleep(500_000);

        // Verify the server is still responding by sending a request
        $response = self::$client->request('workspace/symbol', [
            'query' => '',
        ], static::$requestTimeout);

        self::assertResponseOk($response);
    }

    #[TestDox('Server handles didChangeWatchedFiles with non-PHP files')]
    public function testHandlesNonPhpFilesGracefully(): void
    {
        self::$client->notify('workspace/didChangeWatchedFiles', [
            'changes' => [
                ['uri' => 'file:///tmp/readme.md', 'type' => 2],
            ],
        ]);

        // Small delay to let the server process the notification
        \usleep(500_000);

        // Verify the server is still responding
        $response = self::$client->request('workspace/symbol', [
            'query' => '',
        ], static::$requestTimeout);

        self::assertResponseOk($response);
    }
}

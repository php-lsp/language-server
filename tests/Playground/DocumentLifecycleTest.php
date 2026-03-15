<?php

declare(strict_types=1);

namespace App\Tests\Playground;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\TestDox;

#[Group('e2e')]
#[TestDox('LSP Document Lifecycle')]
final class DocumentLifecycleTest extends PlaygroundTestCase
{
    #[TestDox('Server accepts didOpen and responds to hover')]
    public function testDidOpen(): void
    {
        self::openPlaygroundFile('src/Calculator.php');

        // Verify server processed the file by requesting hover
        $response = self::hover('src/Calculator.php', 9, 8);

        self::assertJsonEquals([
            'result' => [
                'contents' => [
                    'Node classes PhpParser\Node\Stmt\Class_ => PhpParser\Node\Stmt\Namespace_',
                ],
            ],
        ], $response);
    }

    #[TestDox('Server accepts didChange and remains responsive')]
    public function testDidChange(): void
    {
        $uri = self::playgroundFileUri('src/Calculator.php');

        self::$client->notify('textDocument/didChange', [
            'textDocument' => [
                'uri' => $uri,
                'version' => 2,
            ],
            'contentChanges' => [
                [
                    'text' => \file_get_contents(
                        self::getProjectRoot() . '/playground/src/Calculator.php',
                    ),
                ],
            ],
        ]);

        \usleep(300_000);

        $response = self::hover('src/Calculator.php', 9, 8);

        self::assertJsonEquals([
            'result' => [
                'contents' => [
                    'Node classes PhpParser\Node\Stmt\Class_ => PhpParser\Node\Stmt\Namespace_',
                ],
            ],
        ], $response);
    }

    #[TestDox('Server accepts didClose and remains responsive')]
    public function testDidClose(): void
    {
        $uri = self::playgroundFileUri('src/StatusEnum.php');
        $content = \file_get_contents(self::getProjectRoot() . '/playground/src/StatusEnum.php');
        self::$client->openDocument($uri, $content);
        \usleep(300_000);

        self::$client->notify('textDocument/didClose', [
            'textDocument' => ['uri' => $uri],
        ]);

        \usleep(300_000);

        // Server should still be responsive
        self::openPlaygroundFile('src/Greeter.php');
        $response = self::hover('src/Greeter.php', 9, 8);

        self::assertJsonEquals([
            'result' => [
                'contents' => [
                    'Node classes PhpParser\Node\Stmt\Class_ => PhpParser\Node\Stmt\Namespace_',
                ],
            ],
        ], $response);
    }
}

<?php

declare(strict_types=1);

namespace App\Tests\Playground;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\TestDox;

#[Group('e2e')]
#[TestDox('LSP Hover')]
final class HoverTest extends PlaygroundTestCase
{
    protected static float $indexingWaitTime = 3.0;

    private static bool $filesOpened = false;

    protected function setUp(): void
    {
        parent::setUp();

        if (!self::$filesOpened) {
            self::openPlaygroundFile('src/Greeter.php');
            self::openPlaygroundFile('src/Calculator.php');
            self::openPlaygroundFile('src/User.php');
            self::$filesOpened = true;
        }
    }

    #[TestDox('Hover on class name returns AST node trace')]
    public function testHoverOnClassName(): void
    {
        // Greeter.php line 10: "class Greeter"
        $response = self::hover('src/Greeter.php', 9, 8);

        self::assertJsonEquals([
            'result' => [
                'contents' => [
                    'Node classes PhpParser\Node\Stmt\Class_ => PhpParser\Node\Stmt\Namespace_',
                ],
            ],
        ], $response);
    }

    #[TestDox('Hover on method name returns empty contents')]
    public function testHoverOnMethodName(): void
    {
        // Greeter.php line 21: "public function greet"
        $response = self::hover('src/Greeter.php', 20, 24);

        self::assertJsonEquals([
            'result' => [
                'contents' => [],
            ],
        ], $response);
    }

    #[TestDox('Hover on property returns empty contents')]
    public function testHoverOnProperty(): void
    {
        // Greeter.php line 12: "private readonly string $name"
        $response = self::hover('src/Greeter.php', 12, 42);

        self::assertJsonEquals([
            'result' => [
                'contents' => [],
            ],
        ], $response);
    }

    #[TestDox('Hover on empty space returns empty contents')]
    public function testHoverOnEmptySpace(): void
    {
        $response = self::hover('src/Greeter.php', 0, 0);

        self::assertJsonEquals([
            'result' => [
                'contents' => [],
            ],
        ], $response);
    }

    #[TestDox('Hover on Calculator class name returns AST node trace')]
    public function testHoverOnCalculatorClass(): void
    {
        // Calculator.php line 10: "class Calculator"
        $response = self::hover('src/Calculator.php', 9, 8);

        self::assertJsonEquals([
            'result' => [
                'contents' => [
                    'Node classes PhpParser\Node\Stmt\Class_ => PhpParser\Node\Stmt\Namespace_',
                ],
            ],
        ], $response);
    }
}

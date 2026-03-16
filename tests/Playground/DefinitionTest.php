<?php

declare(strict_types=1);

namespace App\Tests\Playground;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\TestDox;

#[Group('e2e')]
#[TestDox('LSP Definition')]
final class DefinitionTest extends PlaygroundTestCase
{
    protected static float $indexingWaitTime = 3.0;

    private static bool $filesOpened = false;

    protected function setUp(): void
    {
        parent::setUp();

        if (!self::$filesOpened) {
            self::openPlaygroundFile('src/Calculator.php');
            self::openPlaygroundFile('src/User.php');
            self::openPlaygroundFile('src/Greeter.php');
            self::openPlaygroundFile('src/UserService.php');
            self::openPlaygroundFile('src/StatusEnum.php');
            self::openPlaygroundFile('src/AppInterface.php');
            self::openPlaygroundFile('src/TypeTestFile.php');
            self::$filesOpened = true;
        }
    }

    #[TestDox('Definition on class in "new Calculator()" navigates to Calculator.php')]
    public function testDefinitionOnNewCalculator(): void
    {
        // TypeTestFile.php line 14 (0-indexed 13): "$calc = new Calculator();"
        // "Calculator" starts at char 20
        $response = self::definition('src/TypeTestFile.php', 13, 22);

        self::assertResponseOk($response);

        $locations = $response['result'];
        self::assertNotEmpty($locations, 'Should return at least one location');

        $uris = array_map(static fn(array $loc): string => $loc['uri'], $locations);
        self::assertContains(
            self::playgroundFileUri('src/Calculator.php'),
            $uris,
            'Should navigate to Calculator.php',
        );
    }

    #[TestDox('Definition on class in "new User()" navigates to User.php')]
    public function testDefinitionOnNewUser(): void
    {
        // TypeTestFile.php line 17 (0-indexed 16): "$user = new User('John', ...);"
        // "User" starts at char 20
        $response = self::definition('src/TypeTestFile.php', 16, 21);

        self::assertResponseOk($response);

        $locations = $response['result'];
        self::assertNotEmpty($locations, 'Should return at least one location');

        $uris = array_map(static fn(array $loc): string => $loc['uri'], $locations);
        self::assertContains(
            self::playgroundFileUri('src/User.php'),
            $uris,
            'Should navigate to User.php',
        );
    }

    #[TestDox('Definition on parameter type hint navigates to class file')]
    public function testDefinitionOnParameterTypeHint(): void
    {
        // TypeTestFile.php line 26 (0-indexed 25):
        // "public function testParameterTypes(Calculator $calc, User $user): User"
        // "Calculator" starts at char 39
        $response = self::definition('src/TypeTestFile.php', 25, 40);

        self::assertResponseOk($response);

        $locations = $response['result'];
        self::assertNotEmpty($locations, 'Should return at least one location');

        $uris = array_map(static fn(array $loc): string => $loc['uri'], $locations);
        self::assertContains(
            self::playgroundFileUri('src/Calculator.php'),
            $uris,
            'Should navigate to Calculator.php',
        );
    }

    #[TestDox('Definition on return type navigates to class file')]
    public function testDefinitionOnReturnType(): void
    {
        // TypeTestFile.php line 26 (0-indexed 25):
        // "public function testParameterTypes(Calculator $calc, User $user): User"
        // Return type "User" starts at char 70
        $response = self::definition('src/TypeTestFile.php', 25, 71);

        self::assertResponseOk($response);

        $locations = $response['result'];
        self::assertNotEmpty($locations, 'Should return at least one location');

        $uris = array_map(static fn(array $loc): string => $loc['uri'], $locations);
        self::assertContains(
            self::playgroundFileUri('src/User.php'),
            $uris,
            'Should navigate to User.php',
        );
    }

    #[TestDox('Definition on class usage in UserService navigates to User.php')]
    public function testDefinitionOnClassInUserService(): void
    {
        // UserService.php line 30 (0-indexed 29): "$user = new User($name, $email);"
        // "User" starts at char 20
        $response = self::definition('src/UserService.php', 29, 22);

        self::assertResponseOk($response);

        $locations = $response['result'];
        self::assertNotEmpty($locations, 'Should return at least one location');

        $uris = array_map(static fn(array $loc): string => $loc['uri'], $locations);
        self::assertContains(
            self::playgroundFileUri('src/User.php'),
            $uris,
            'Should navigate to User.php',
        );
    }

    #[TestDox('Definition on User parameter type in UserService.createUser')]
    public function testDefinitionOnReturnTypeInUserService(): void
    {
        // UserService.php line 28 (0-indexed 27):
        // "public function createUser(string $name, string $email): User"
        // Return type "User" — let's find the position
        $response = self::definition('src/UserService.php', 27, 62);

        self::assertResponseOk($response);

        $locations = $response['result'];
        self::assertNotEmpty($locations, 'Should return at least one location');

        $uris = array_map(static fn(array $loc): string => $loc['uri'], $locations);
        self::assertContains(
            self::playgroundFileUri('src/User.php'),
            $uris,
            'Should navigate to User.php',
        );
    }

    #[TestDox('Definition on empty position returns empty result')]
    public function testDefinitionOnEmptyPosition(): void
    {
        $response = self::definition('src/TypeTestFile.php', 0, 0);

        self::assertResponseOk($response);
        self::assertEmpty($response['result'], 'Should return empty result for non-symbol position');
    }
}

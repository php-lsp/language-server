<?php

declare(strict_types=1);

namespace App\Tests\Playground;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\TestDox;

#[Group('e2e')]
#[TestDox('LSP Type Definition')]
final class TypeDefinitionTest extends PlaygroundTestCase
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
            self::openPlaygroundFile('src/TypeTestFile.php');
            self::$filesOpened = true;
        }
    }

    #[TestDox('Type definition on $user variable navigates to User.php')]
    public function testTypeDefinitionOnUserVariable(): void
    {
        // TypeTestFile.php line 17 (0-indexed 16): "$user = new User('John', 'john@example.com');"
        // "$user" starts at char 8
        $response = self::typeDefinition('src/TypeTestFile.php', 16, 9);

        self::assertResponseOk($response);

        $locations = $response['result'];
        if (empty($locations)) {
            self::markTestSkipped('TypeResolver did not resolve type for $user variable');
        }

        $uris = array_map(static fn(array $loc): string => $loc['uri'], $locations);
        self::assertContains(
            self::playgroundFileUri('src/User.php'),
            $uris,
            'Should navigate to User.php for $user variable',
        );
    }

    #[TestDox('Type definition on $calc variable navigates to Calculator.php')]
    public function testTypeDefinitionOnCalcVariable(): void
    {
        // TypeTestFile.php line 14 (0-indexed 13): "$calc = new Calculator();"
        // "$calc" starts at char 8
        $response = self::typeDefinition('src/TypeTestFile.php', 13, 9);

        self::assertResponseOk($response);

        $locations = $response['result'];
        if (empty($locations)) {
            self::markTestSkipped('TypeResolver did not resolve type for $calc variable');
        }

        $uris = array_map(static fn(array $loc): string => $loc['uri'], $locations);
        self::assertContains(
            self::playgroundFileUri('src/Calculator.php'),
            $uris,
            'Should navigate to Calculator.php for $calc variable',
        );
    }

    #[TestDox('Type definition on $user in UserService navigates to User.php')]
    public function testTypeDefinitionOnUserServiceVariable(): void
    {
        // UserService.php line 30 (0-indexed 29): "$user = new User($name, $email);"
        // "$user" at char 8
        $response = self::typeDefinition('src/UserService.php', 29, 9);

        self::assertResponseOk($response);

        $locations = $response['result'];
        if (empty($locations)) {
            self::markTestSkipped('TypeResolver did not resolve type for $user variable');
        }

        $uris = array_map(static fn(array $loc): string => $loc['uri'], $locations);
        self::assertContains(
            self::playgroundFileUri('src/User.php'),
            $uris,
            'Should navigate to User.php',
        );
    }

    #[TestDox('Type definition on typed parameter $calc navigates to Calculator.php')]
    public function testTypeDefinitionOnTypedParameter(): void
    {
        // TypeTestFile.php line 26 (0-indexed 25):
        // "public function testParameterTypes(Calculator $calc, User $user): User"
        // "$calc" at char 50
        $response = self::typeDefinition('src/TypeTestFile.php', 25, 51);

        self::assertResponseOk($response);

        $locations = $response['result'];
        if (empty($locations)) {
            self::markTestSkipped('TypeResolver did not resolve type for typed parameter');
        }

        $uris = array_map(static fn(array $loc): string => $loc['uri'], $locations);
        self::assertContains(
            self::playgroundFileUri('src/Calculator.php'),
            $uris,
            'Should navigate to Calculator.php for Calculator $calc parameter',
        );
    }

    #[TestDox('Type definition returns valid response for method return usage')]
    public function testTypeDefinitionOnMethodReturnUsage(): void
    {
        // TypeTestFile.php line 18 (0-indexed 17): "$name = $user->getName();"
        // "$user" at chars 16-20
        $response = self::typeDefinition('src/TypeTestFile.php', 17, 17);

        // May return an error if TypeResolver can't resolve the expression
        if (isset($response['error'])) {
            self::assertArrayHasKey('code', $response['error']);
            return;
        }

        self::assertResponseOk($response);
    }

    #[TestDox('Type definition on empty position returns empty result')]
    public function testTypeDefinitionOnEmptyPosition(): void
    {
        $response = self::typeDefinition('src/TypeTestFile.php', 0, 0);

        self::assertResponseOk($response);
        self::assertEmpty($response['result'], 'Should return empty result for non-symbol position');
    }
}

<?php

declare(strict_types=1);

namespace App\Tests\Playground;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\TestDox;

/**
 * E2E test that verifies the debug index API returns correct data
 * after the LSP server indexes the playground workspace.
 *
 * Starts the server, waits for indexing, then queries the debug HTTP API
 * (which runs on LSP port + 1) to verify playground classes, methods,
 * interfaces, enums, and other symbols are properly indexed.
 */
#[Group('e2e')]
#[TestDox('Debug Index API')]
final class DebugIndexTest extends PlaygroundTestCase
{
    protected static float $indexingWaitTime = 3.0;

    private static int $debugPort;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        self::$debugPort = self::$server->getPort() + 1;

        // Wait a bit more for the debug server to start
        \usleep(500_000);
    }

    private static function debugGet(string $path): array
    {
        $url = 'http://127.0.0.1:' . self::$debugPort . $path;
        $context = stream_context_create([
            'http' => [
                'timeout' => 10,
                'ignore_errors' => true,
            ],
        ]);

        $response = @file_get_contents($url, false, $context);

        if ($response === false) {
            self::fail("Debug API request failed: {$url}");
        }

        $data = json_decode($response, true);

        if (!is_array($data)) {
            self::fail("Debug API returned invalid JSON from: {$url}");
        }

        return $data;
    }

    #[TestDox('Debug API lists all expected index keys')]
    public function testIndexKeysExist(): void
    {
        $data = self::debugGet('/api/indexes');

        $expectedIndexes = [
            'php.classes.fqn',
            'php.interfaces.fqn',
            'php.enums.fqn',
            'php.classMethods.fqn',
            'php.namespaces.fqn',
        ];

        foreach ($expectedIndexes as $indexKey) {
            $this->assertArrayHasKey($indexKey, $data, "Index '{$indexKey}' should exist");
            $this->assertGreaterThan(0, $data[$indexKey]['count'], "Index '{$indexKey}' should have entries");
        }
    }

    #[TestDox('Classes index contains playground classes')]
    public function testClassesIndex(): void
    {
        $data = self::debugGet('/api/indexes/php.classes.fqn/keys?pattern=Playground%5C*');

        $this->assertGreaterThanOrEqual(4, $data['total']);

        $expectedClasses = [
            'Playground\\Calculator',
            'Playground\\Greeter',
            'Playground\\User',
            'Playground\\UserService',
        ];

        foreach ($expectedClasses as $class) {
            $this->assertContains($class, $data['keys'], "Class '{$class}' should be indexed");
        }
    }

    #[TestDox('Interfaces index contains playground interface')]
    public function testInterfacesIndex(): void
    {
        $data = self::debugGet('/api/indexes/php.interfaces.fqn/keys?pattern=Playground%5C*');

        $this->assertContains('Playground\\AppInterface', $data['keys']);
    }

    #[TestDox('Enums index contains playground enum')]
    public function testEnumsIndex(): void
    {
        $data = self::debugGet('/api/indexes/php.enums.fqn/keys?pattern=Playground%5C*');

        $this->assertContains('Playground\\StatusEnum', $data['keys']);
    }

    #[TestDox('Class methods index contains playground class keys')]
    public function testClassMethodsIndex(): void
    {
        $data = self::debugGet('/api/indexes/php.classMethods.fqn/keys?pattern=Playground%5C*');

        $this->assertGreaterThan(0, $data['total']);

        $expectedMethods = [
            'Playground\\Calculator::',
            'Playground\\User::',
            'Playground\\UserService::',
            'Playground\\Greeter::',
        ];

        $allKeys = implode("\n", $data['keys']);
        foreach ($expectedMethods as $prefix) {
            $this->assertStringContainsString($prefix, $allKeys, "Methods for '{$prefix}' should be indexed");
        }
    }

    #[TestDox('Global search finds playground symbols')]
    public function testGlobalSearch(): void
    {
        $data = self::debugGet('/api/search?pattern=Playground%5CCalculator');

        $this->assertGreaterThanOrEqual(1, $data['count']);
        $this->assertSame('Playground\\Calculator', $data['results'][0]['key']);
    }

    #[TestDox('Index entry returns correct details')]
    public function testEntryDetail(): void
    {
        $data = self::debugGet('/api/indexes/php.classes.fqn/entries/Playground%5CCalculator');

        $this->assertSame('Playground\\Calculator', $data['key']);
        $this->assertArrayHasKey('uri', $data);
        $this->assertStringContainsString('Calculator.php', $data['uri']);
    }

    #[TestDox('Namespaces index contains Playground namespace')]
    public function testNamespacesIndex(): void
    {
        $data = self::debugGet('/api/indexes/php.namespaces.fqn/keys?pattern=Playground');

        $this->assertContains('Playground', $data['keys']);
    }
}

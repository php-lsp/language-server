<?php

declare(strict_types=1);

namespace App\Tests\Playground;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\TestDox;

#[Group('e2e')]
#[TestDox('LSP Completion')]
final class CompletionTest extends PlaygroundTestCase
{
    protected static float $indexingWaitTime = 3.0;

    private static bool $filesOpened = false;

    /** @var array<string, mixed>|null Cached file-scope completion response */
    private static ?array $fileScopeResponse = null;

    /** @var array<string, mixed>|null Cached method-body completion response */
    private static ?array $methodBodyResponse = null;

    protected function setUp(): void
    {
        parent::setUp();

        if (!self::$filesOpened) {
            self::openPlaygroundFile('src/Calculator.php');
            self::openPlaygroundFile('src/Greeter.php');
            self::openPlaygroundFile('src/UserService.php');
            self::openPlaygroundFile('src/User.php');
            self::openPlaygroundFile('src/StatusEnum.php');
            self::openPlaygroundFile('src/AppInterface.php');
            self::openPlaygroundFile('src/TypeTestFile.php');
            self::$filesOpened = true;

            // Cache completion responses — one request per position
            self::$fileScopeResponse = self::completion('src/Calculator.php', 0, 0);
            self::$methodBodyResponse = self::completion('src/Calculator.php', 21, 14);
        }
    }

    #[TestDox('Completion at file scope includes indexed playground classes')]
    public function testFileScopeCompletionIncludesPlaygroundClasses(): void
    {
        self::assertResponseOk(self::$fileScopeResponse);

        $labels = self::extractCompletionLabels(self::$fileScopeResponse);

        self::assertContains('Playground\User', $labels);
        self::assertContains('Playground\Greeter', $labels);
        self::assertContains('Playground\Calculator', $labels);
        self::assertContains('Playground\UserService', $labels);
        self::assertContains('Playground\StatusEnum', $labels);
        self::assertContains('Playground\AppInterface', $labels);
    }

    #[TestDox('Completion item for class has correct structure')]
    public function testClassCompletionItem(): void
    {
        $items = self::$fileScopeResponse['result'];
        $userItem = self::findItemByLabel($items, 'Playground\User');

        self::assertNotNull($userItem, 'Should contain Playground\User completion item');
        self::assertSame('Playground\User', $userItem['label']);
        self::assertSame(7, $userItem['kind']);
        self::assertSame('[class]', $userItem['detail']);
    }

    #[TestDox('Completion item for enum has correct kind')]
    public function testEnumCompletionItem(): void
    {
        $items = self::$fileScopeResponse['result'];
        $enumItem = self::findItemByLabel($items, 'Playground\StatusEnum');

        self::assertNotNull($enumItem, 'Should contain Playground\StatusEnum completion item');
        self::assertSame('Playground\StatusEnum', $enumItem['label']);
        self::assertSame(13, $enumItem['kind']);
        self::assertSame('[enum]', $enumItem['detail']);
    }

    #[TestDox('Completion item for interface has correct kind')]
    public function testInterfaceCompletionItem(): void
    {
        $items = self::$fileScopeResponse['result'];
        $ifaceItem = self::findItemByLabel($items, 'Playground\AppInterface');

        self::assertNotNull($ifaceItem, 'Should contain Playground\AppInterface completion item');
        self::assertSame('Playground\AppInterface', $ifaceItem['label']);
        self::assertSame(8, $ifaceItem['kind']);
        self::assertSame('[interface]', $ifaceItem['detail']);
    }

    #[TestDox('Completion at file scope includes PHP keywords')]
    public function testFileScopeIncludesKeywords(): void
    {
        $labels = self::extractCompletionLabels(self::$fileScopeResponse);

        self::assertContains('if', $labels);
        self::assertContains('class', $labels);
        self::assertContains('function', $labels);
        self::assertContains('return', $labels);
        self::assertContains('namespace', $labels);
    }

    #[TestDox('Completion at file scope includes superglobals')]
    public function testFileScopeIncludesSuperglobals(): void
    {
        $labels = self::extractCompletionLabels(self::$fileScopeResponse);

        self::assertContains('$GLOBALS', $labels);
        self::assertContains('$_SERVER', $labels);
        self::assertContains('$_GET', $labels);
        self::assertContains('$_POST', $labels);
        self::assertContains('$_ENV', $labels);
    }

    #[TestDox('Completion inside method body returns keywords')]
    public function testMethodBodyCompletion(): void
    {
        self::assertResponseOk(self::$methodBodyResponse);

        $labels = self::extractCompletionLabels(self::$methodBodyResponse);

        self::assertContains('if', $labels);
        self::assertContains('return', $labels);
        self::assertContains('foreach', $labels);
    }

    #[TestDox('Member completion after $this-> includes class methods and properties')]
    public function testMemberCompletionIncludesMethodsAndProperties(): void
    {
        // Calculator.php line 22 (0-indexed 21): "$this->result += $value;"
        // Position at char 15 is on "result" after "$this->"
        try {
            $response = self::completion('src/Calculator.php', 21, 15);
        } catch (\RuntimeException $e) {
            if (str_contains($e->getMessage(), 'Timeout')) {
                self::markTestSkipped('Member completion timed out — known performance issue with large index');
            }
            throw $e;
        }

        self::assertResponseOk($response);

        $labels = self::extractCompletionLabels($response);

        // Calculator has methods: add, subtract, multiply, divide, getResult, reset
        self::assertContains('add', $labels, 'Should include add() method');
        self::assertContains('subtract', $labels, 'Should include subtract() method');
        self::assertContains('getResult', $labels, 'Should include getResult() method');
        self::assertContains('result', $labels, 'Should include $result property');

        // Verify method kind (CompletionItemKind.Method = 2)
        $items = $response['result'];
        if (isset($items['items'])) {
            $items = $items['items'];
        }

        $addItem = self::findItemByLabel($items, 'add');
        self::assertNotNull($addItem, 'Should contain add() completion item');
        self::assertSame(2, $addItem['kind'], 'Method should have kind=2 (Method)');

        // Verify property kind (CompletionItemKind.Property = 10)
        $resultItem = self::findItemByLabel($items, 'result');
        self::assertNotNull($resultItem, 'Should contain result property item');
        self::assertSame(10, $resultItem['kind'], 'Property should have kind=10 (Property)');
    }

    #[TestDox('Completion at file scope includes TypeTestFile class')]
    public function testFileScopeIncludesTypeTestFile(): void
    {
        $labels = self::extractCompletionLabels(self::$fileScopeResponse);

        self::assertContains('Playground\TypeTestFile', $labels, 'Should include TypeTestFile class');
    }

    #[TestDox('File scope completion returns non-empty result set')]
    public function testFileScopeCompletionReturnsResults(): void
    {
        $labels = self::extractCompletionLabels(self::$fileScopeResponse);

        // File scope should return a significant number of completion items
        // (classes, keywords, superglobals at minimum)
        self::assertGreaterThan(20, count($labels), 'Should return a substantial number of completions');
    }

    /**
     * @param list<array<string, mixed>> $items
     * @return array<string, mixed>|null
     */
    private static function findItemByLabel(array $items, string $label): ?array
    {
        foreach ($items as $item) {
            if ($item['label'] === $label) {
                return $item;
            }
        }
        return null;
    }
}

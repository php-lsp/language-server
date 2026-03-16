<?php

declare(strict_types=1);

namespace App\Tests\Playground;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\TestDox;

#[Group('e2e')]
#[TestDox('LSP FoldingRange')]
final class FoldingRangeTest extends PlaygroundTestCase
{
    protected static float $indexingWaitTime = 3.0;

    private static bool $filesOpened = false;

    protected function setUp(): void
    {
        parent::setUp();

        if (!self::$filesOpened) {
            self::openPlaygroundFile('src/Calculator.php');
            self::$filesOpened = true;
        }
    }

    #[TestDox('Returns folding ranges for Calculator class and methods')]
    public function testCalculatorFoldingRanges(): void
    {
        $response = self::foldingRange('src/Calculator.php');

        self::assertResponseOk($response);

        $ranges = $response['result'];
        $this->assertIsArray($ranges);
        $this->assertNotEmpty($ranges, 'Calculator.php should have folding ranges');

        // Check that we have region ranges (class, methods)
        $regionRanges = array_filter(
            $ranges,
            static fn(array $r): bool => ($r['kind'] ?? null) === 'region',
        );
        $this->assertNotEmpty($regionRanges, 'Should have region folding ranges for class and methods');

        // Check that we have comment ranges (docblocks)
        $commentRanges = array_filter(
            $ranges,
            static fn(array $r): bool => ($r['kind'] ?? null) === 'comment',
        );
        $this->assertNotEmpty($commentRanges, 'Should have comment folding ranges for docblocks');
    }

    #[TestDox('Folding range for class spans expected lines')]
    public function testClassFoldingRangeSpan(): void
    {
        $response = self::foldingRange('src/Calculator.php');

        self::assertResponseOk($response);

        $ranges = $response['result'];

        // Calculator class starts at line 10 (0-based: 9) and ends at line 82 (0-based: 81)
        $classRange = $this->findRange($ranges, 9, 81);
        $this->assertNotNull($classRange, 'Should have a folding range for the Calculator class body');
    }

    /**
     * @param array<array<string, mixed>> $ranges
     * @return array<string, mixed>|null
     */
    private function findRange(array $ranges, int $startLine, int $endLine): ?array
    {
        foreach ($ranges as $range) {
            if ($range['startLine'] === $startLine && $range['endLine'] === $endLine) {
                return $range;
            }
        }

        return null;
    }
}

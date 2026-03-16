<?php

declare(strict_types=1);

namespace App\Tests\Unit\Core\Contracts\FoldingRange;

use App\Core\Contracts\FoldingRange\FoldingRangeConsumer;
use App\Tests\TestCase;
use Lsp\Protocol\Type\FoldingRange;
use Lsp\Protocol\Type\FoldingRangeKind;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\TestDox;

#[Group('unit')]
final class FoldingRangeConsumerTest extends TestCase
{
    #[TestDox('starts with empty results')]
    public function testStartsEmpty(): void
    {
        $consumer = new FoldingRangeConsumer();

        $this->assertSame([], $consumer->results);
    }

    #[TestDox('collects folding ranges via __invoke')]
    public function testCollectsRanges(): void
    {
        $consumer = new FoldingRangeConsumer();
        $range = new FoldingRange(startLine: 0, endLine: 10, kind: FoldingRangeKind::Region);

        $consumer($range);

        $this->assertCount(1, $consumer->results);
        $this->assertSame($range, $consumer->results[0]);
    }

    #[TestDox('accepts multiple ranges at once')]
    public function testAcceptsMultiple(): void
    {
        $consumer = new FoldingRangeConsumer();
        $r1 = new FoldingRange(startLine: 0, endLine: 5, kind: FoldingRangeKind::Region);
        $r2 = new FoldingRange(startLine: 6, endLine: 10, kind: FoldingRangeKind::Comment);

        $consumer($r1, $r2);

        $this->assertCount(2, $consumer->results);
    }
}

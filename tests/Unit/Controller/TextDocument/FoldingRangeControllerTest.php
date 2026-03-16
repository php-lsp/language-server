<?php

declare(strict_types=1);

namespace App\Tests\Unit\Controller\TextDocument;

use App\Controller\TextDocument\FoldingRangeController;
use App\Core\Contracts\FoldingRange\FoldingRangeConsumer;
use App\Core\Contracts\FoldingRange\FoldingRangeContext;
use App\Core\Contracts\FoldingRange\FoldingRangeContributor;
use App\Core\Contracts\PsiFile\PsiFileManagerInterface;
use App\Module\Telemetry\NoopTracer;
use App\Tests\Support\MockHelper;
use App\Tests\Support\ProtocolFactory;
use App\Tests\TestCase;
use Lsp\Extension\DocumentManager\Editor\EditorInterface;
use Lsp\Protocol\Type\FoldingRange;
use Lsp\Protocol\Type\FoldingRangeKind;
use Lsp\Protocol\Type\FoldingRangeParams;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\TestDox;

#[Group('unit')]
final class FoldingRangeControllerTest extends TestCase
{
    #[TestDox('returns empty array with no contributors')]
    public function testReturnsEmptyWithNoContributors(): void
    {
        $fileManager = MockHelper::mock(PsiFileManagerInterface::class);
        $controller = new FoldingRangeController([], $fileManager, new NoopTracer());
        $editor = MockHelper::mock(EditorInterface::class);
        $params = new FoldingRangeParams(
            textDocument: ProtocolFactory::textDocumentIdentifier(),
        );

        $result = $controller($editor, $params);

        $this->assertSame([], $result);
    }

    #[TestDox('aggregates results from contributors')]
    public function testAggregatesContributors(): void
    {
        $range = new FoldingRange(startLine: 0, endLine: 10, kind: FoldingRangeKind::Region);
        $contributor = new class($range) implements FoldingRangeContributor {
            public function __construct(private FoldingRange $range) {}
            public function contribute(FoldingRangeContext $context, FoldingRangeConsumer $consumer): void
            {
                $consumer($this->range);
            }
        };

        $fileManager = MockHelper::mock(PsiFileManagerInterface::class);
        $controller = new FoldingRangeController([$contributor], $fileManager, new NoopTracer());
        $editor = MockHelper::mock(EditorInterface::class);
        $params = new FoldingRangeParams(
            textDocument: ProtocolFactory::textDocumentIdentifier(),
        );

        $result = $controller($editor, $params);

        $this->assertCount(1, $result);
        $this->assertSame($range, $result[0]);
    }
}

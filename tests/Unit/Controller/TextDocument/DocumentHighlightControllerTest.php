<?php

declare(strict_types=1);

namespace App\Tests\Unit\Controller\TextDocument;

use App\Controller\TextDocument\DocumentHighlightController;
use App\Core\Contracts\Highlight\DocumentHighlightConsumer;
use App\Core\Contracts\Highlight\DocumentHighlightContext;
use App\Core\Contracts\Highlight\DocumentHighlightContributor;
use App\Tests\Support\MockHelper;
use App\Tests\Support\ProtocolFactory;
use App\Tests\TestCase;
use Lsp\Extension\DocumentManager\Editor\EditorInterface;
use Lsp\Protocol\Type\DocumentHighlight;
use Lsp\Protocol\Type\DocumentHighlightKind;
use Lsp\Protocol\Type\DocumentHighlightParams;
use App\Module\Telemetry\NoopTracer;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\TestDox;

#[Group('unit')]
final class DocumentHighlightControllerTest extends TestCase
{
    #[TestDox('returns empty array with no contributors')]
    public function testReturnsEmptyWithNoContributors(): void
    {
        $fileManager = MockHelper::mock(\App\Module\PsiFile\InMemoryPsiFileManager::class);
        $controller = new DocumentHighlightController([], $fileManager, new NoopTracer());
        $editor = MockHelper::mock(EditorInterface::class);
        $params = new DocumentHighlightParams(
            textDocument: ProtocolFactory::textDocumentIdentifier(),
            position: ProtocolFactory::position(),
        );

        $result = $controller($editor, $params);

        $this->assertSame([], $result);
    }

    #[TestDox('aggregates results from contributors')]
    public function testAggregatesContributors(): void
    {
        $highlight = new DocumentHighlight(
            range: ProtocolFactory::range(),
            kind: DocumentHighlightKind::Text,
        );
        $contributor = new class($highlight) implements DocumentHighlightContributor {
            public function __construct(private $h) {}
            public function contribute(DocumentHighlightContext $context, DocumentHighlightConsumer $consumer): void
            {
                $consumer->results[] = $this->h;
            }
        };

        $fileManager = MockHelper::mock(\App\Module\PsiFile\InMemoryPsiFileManager::class);
        $controller = new DocumentHighlightController([$contributor], $fileManager, new NoopTracer());
        $editor = MockHelper::mock(EditorInterface::class);
        $params = new DocumentHighlightParams(
            textDocument: ProtocolFactory::textDocumentIdentifier(),
            position: ProtocolFactory::position(),
        );

        $result = $controller($editor, $params);

        $this->assertCount(1, $result);
        $this->assertSame($highlight, $result[0]);
    }
}

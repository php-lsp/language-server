<?php

declare(strict_types=1);

namespace App\Tests\Unit\Controller\TextDocument;

use App\Controller\TextDocument\ReferencesController;
use App\Core\Contracts\References\ReferenceConsumer;
use App\Core\Contracts\References\ReferenceContext;
use App\Core\Contracts\References\ReferenceContributor;
use App\Tests\Support\ProtocolFactory;
use App\Tests\TestCase;
use Lsp\Extension\DocumentManager\Editor\EditorInterface;
use Lsp\Protocol\Type\ReferenceParams;
use Lsp\Protocol\Type\ReferenceContext as LspReferenceContext;
use App\Module\Telemetry\NoopTracer;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\TestDox;

#[Group('unit')]
final class ReferencesControllerTest extends TestCase
{
    #[TestDox('returns empty when no contributors')]
    public function testEmptyContributors(): void
    {
        $controller = new ReferencesController([], new NoopTracer());
        $editor = $this->createMock(EditorInterface::class);
        $params = new ReferenceParams(
            context: new LspReferenceContext(includeDeclaration: false),
            textDocument: ProtocolFactory::textDocumentIdentifier(),
            position: ProtocolFactory::position(),
        );

        $result = $controller($editor, $params);

        $this->assertSame([], $result);
    }

    #[TestDox('aggregates results from contributors')]
    public function testAggregatesContributors(): void
    {
        $contributor = new class implements ReferenceContributor {
            public function contribute(\App\Core\Contracts\References\ReferenceContext $context, ReferenceConsumer $consumer): void
            {
                $consumer(new \Lsp\Protocol\Type\Location(uri: 'file:///foo.php', range: ProtocolFactory::range()));
            }
        };

        $controller = new ReferencesController([$contributor], new NoopTracer());
        $editor = $this->createMock(EditorInterface::class);
        $params = new ReferenceParams(
            context: new LspReferenceContext(includeDeclaration: false),
            textDocument: ProtocolFactory::textDocumentIdentifier(),
            position: ProtocolFactory::position(),
        );

        $result = $controller($editor, $params);

        $this->assertCount(1, $result);
    }
}

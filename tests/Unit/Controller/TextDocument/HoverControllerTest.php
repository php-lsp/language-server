<?php

declare(strict_types=1);

namespace App\Tests\Unit\Controller\TextDocument;

use App\Controller\TextDocument\HoverController;
use App\Core\Contracts\Documentation\DocumentationConsumer;
use App\Core\Contracts\Documentation\DocumentationContext;
use App\Core\Contracts\Documentation\DocumentationContributor;
use App\Tests\Support\MockHelper;
use App\Tests\Support\ProtocolFactory;
use App\Tests\TestCase;
use Lsp\Extension\DocumentManager\Editor\EditorInterface;
use Lsp\Protocol\Type\Hover;
use Lsp\Protocol\Type\HoverParams;
use App\Module\Telemetry\NoopTracer;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\TestDox;

#[Group('unit')]
final class HoverControllerTest extends TestCase
{
    #[TestDox('returns Hover with empty content when no contributors')]
    public function testReturnsEmptyHover(): void
    {
        $controller = new HoverController([], new NoopTracer());
        $editor = MockHelper::mock(EditorInterface::class);
        $params = new HoverParams(
            textDocument: ProtocolFactory::textDocumentIdentifier(),
            position: ProtocolFactory::position(),
        );

        $result = $controller($editor, $params);

        $this->assertInstanceOf(Hover::class, $result);
        $this->assertSame([], $result->contents);
    }

    #[TestDox('aggregates documentation from contributors')]
    public function testAggregatesContributors(): void
    {
        $contributor = new class implements DocumentationContributor {
            public function contribute(DocumentationContext $context, DocumentationConsumer $consumer): void
            {
                ($consumer)('Some docs');
            }
        };

        $controller = new HoverController([$contributor], new NoopTracer());
        $editor = MockHelper::mock(EditorInterface::class);
        $params = new HoverParams(
            textDocument: ProtocolFactory::textDocumentIdentifier(),
            position: ProtocolFactory::position(),
        );

        $result = $controller($editor, $params);

        $this->assertInstanceOf(Hover::class, $result);
        $this->assertSame(['Some docs'], $result->contents);
    }
}

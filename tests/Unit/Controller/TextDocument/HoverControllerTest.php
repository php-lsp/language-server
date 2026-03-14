<?php

declare(strict_types=1);

namespace App\Tests\Unit\Controller\TextDocument;

use App\Controller\TextDocument\HoverController;
use App\Core\Contracts\Documentation\DocumentationConsumer;
use App\Core\Contracts\Documentation\DocumentationContext;
use App\Core\Contracts\Documentation\DocumentationContributor;
use App\Tests\Support\ProtocolFactory;
use App\Tests\TestCase;
use Lsp\Extension\DocumentManager\Editor\EditorInterface;
use Lsp\Protocol\Type\Hover;
use Lsp\Protocol\Type\HoverParams;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\TestDox;

#[Group('unit')]
final class HoverControllerTest extends TestCase
{
    #[TestDox('returns hover with empty results when no contributors')]
    public function testEmptyContributors(): void
    {
        $controller = new HoverController([]);
        $editor = $this->createMock(EditorInterface::class);
        $params = new HoverParams(
            textDocument: ProtocolFactory::textDocumentIdentifier(),
            position: ProtocolFactory::position(),
        );

        $result = $controller($editor, $params);

        $this->assertInstanceOf(Hover::class, $result);
    }

    #[TestDox('aggregates results from contributors')]
    public function testAggregatesContributors(): void
    {
        $contributor = new class implements DocumentationContributor {
            public function contribute(DocumentationContext $context, DocumentationConsumer $consumer): void
            {
                $consumer('hover info');
            }
        };

        $controller = new HoverController([$contributor]);
        $editor = $this->createMock(EditorInterface::class);
        $params = new HoverParams(
            textDocument: ProtocolFactory::textDocumentIdentifier(),
            position: ProtocolFactory::position(),
        );

        $result = $controller($editor, $params);

        $this->assertSame(['hover info'], $result->contents);
    }
}

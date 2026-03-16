<?php

declare(strict_types=1);

namespace App\Tests\Unit\Controller\TextDocument;

use App\Controller\TextDocument\DeclarationController;
use App\Core\Contracts\Declaration\DeclarationConsumer;
use App\Core\Contracts\Declaration\DeclarationContext;
use App\Core\Contracts\Declaration\DeclarationContributor;
use App\Tests\Support\MockHelper;
use App\Tests\Support\ProtocolFactory;
use App\Tests\TestCase;
use Lsp\Extension\DocumentManager\Editor\EditorInterface;
use Lsp\Protocol\Type\DeclarationParams;
use App\Module\Telemetry\NoopTracer;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\TestDox;

#[Group('unit')]
final class DeclarationControllerTest extends TestCase
{
    #[TestDox('returns empty array with no contributors')]
    public function testReturnsEmptyWithNoContributors(): void
    {
        $controller = new DeclarationController([], new NoopTracer());
        $editor = MockHelper::mock(EditorInterface::class);
        $params = new DeclarationParams(
            textDocument: ProtocolFactory::textDocumentIdentifier(),
            position: ProtocolFactory::position(),
        );

        $result = $controller($editor, $params);

        $this->assertSame([], $result);
    }

    #[TestDox('aggregates results from contributors')]
    public function testAggregatesContributors(): void
    {
        $location = ProtocolFactory::location();
        $contributor = new class($location) implements DeclarationContributor {
            public function __construct(private $loc) {}
            public function contribute(DeclarationContext $context, DeclarationConsumer $consumer): void
            {
                $consumer->results[] = $this->loc;
            }
        };

        $controller = new DeclarationController([$contributor], new NoopTracer());
        $editor = MockHelper::mock(EditorInterface::class);
        $params = new DeclarationParams(
            textDocument: ProtocolFactory::textDocumentIdentifier(),
            position: ProtocolFactory::position(),
        );

        $result = $controller($editor, $params);

        $this->assertCount(1, $result);
        $this->assertSame($location, $result[0]);
    }
}

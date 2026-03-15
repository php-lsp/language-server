<?php

declare(strict_types=1);

namespace App\Tests\Unit\Controller\TextDocument;

use App\Controller\TextDocument\DefinitionController;
use App\Core\Contracts\Definition\DefinitionConsumer;
use App\Core\Contracts\Definition\DefinitionContext;
use App\Core\Contracts\Definition\DefinitionContributor;
use App\Tests\Support\MockHelper;
use App\Tests\Support\ProtocolFactory;
use App\Tests\TestCase;
use Lsp\Extension\DocumentManager\Editor\EditorInterface;
use Lsp\Protocol\Type\DefinitionParams;
use App\Module\Telemetry\NoopTracer;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\TestDox;

#[Group('unit')]
final class DefinitionControllerTest extends TestCase
{
    #[TestDox('returns empty array with no contributors')]
    public function testReturnsEmptyWithNoContributors(): void
    {
        $controller = new DefinitionController([], new NoopTracer());
        $editor = MockHelper::mock(EditorInterface::class);
        $params = new DefinitionParams(
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
        $contributor = new class($location) implements DefinitionContributor {
            public function __construct(private $loc) {}
            public function contribute(DefinitionContext $context, DefinitionConsumer $consumer): void
            {
                $consumer->results[] = $this->loc;
            }
        };

        $controller = new DefinitionController([$contributor], new NoopTracer());
        $editor = MockHelper::mock(EditorInterface::class);
        $params = new DefinitionParams(
            textDocument: ProtocolFactory::textDocumentIdentifier(),
            position: ProtocolFactory::position(),
        );

        $result = $controller($editor, $params);

        $this->assertCount(1, $result);
        $this->assertSame($location, $result[0]);
    }
}

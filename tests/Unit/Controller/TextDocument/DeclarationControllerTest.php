<?php

declare(strict_types=1);

namespace App\Tests\Unit\Controller\TextDocument;

use App\Controller\TextDocument\DeclarationController;
use App\Core\Contracts\Declaration\DeclarationConsumer;
use App\Core\Contracts\Declaration\DeclarationContext;
use App\Core\Contracts\Declaration\DeclarationContributor;
use App\Tests\Support\ProtocolFactory;
use App\Tests\TestCase;
use Lsp\Extension\DocumentManager\Editor\EditorInterface;
use Lsp\Protocol\Type\DeclarationParams;
use Lsp\Protocol\Type\Location;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\TestDox;

#[Group('unit')]
final class DeclarationControllerTest extends TestCase
{
    #[TestDox('returns empty when no contributors')]
    public function testEmptyContributors(): void
    {
        $controller = new DeclarationController([]);
        $editor = $this->createMock(EditorInterface::class);
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
        $contributor = new class implements DeclarationContributor {
            public function contribute(DeclarationContext $context, DeclarationConsumer $consumer): void
            {
                $consumer(new Location(uri: 'file:///foo.php', range: ProtocolFactory::range()));
            }
        };

        $controller = new DeclarationController([$contributor]);
        $editor = $this->createMock(EditorInterface::class);
        $params = new DeclarationParams(
            textDocument: ProtocolFactory::textDocumentIdentifier(),
            position: ProtocolFactory::position(),
        );

        $result = $controller($editor, $params);

        $this->assertCount(1, $result);
    }
}

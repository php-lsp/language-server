<?php

declare(strict_types=1);

namespace App\Tests\Unit\Controller\TextDocument;

use App\Controller\TextDocument\SignatureHelpController;
use App\Tests\Support\ProtocolFactory;
use App\Tests\TestCase;
use Lsp\Extension\DocumentManager\Editor\EditorInterface;
use Lsp\Protocol\Type\SignatureHelp;
use Lsp\Protocol\Type\SignatureHelpParams;
use App\Module\Telemetry\NoopTracer;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\TestDox;

#[Group('unit')]
final class SignatureHelpControllerTest extends TestCase
{
    #[TestDox('returns signature help with empty signatures when no contributors')]
    public function testEmptyContributors(): void
    {
        $controller = new SignatureHelpController([], new NoopTracer());
        $editor = $this->createMock(EditorInterface::class);
        $params = new SignatureHelpParams(
            textDocument: ProtocolFactory::textDocumentIdentifier(),
            position: ProtocolFactory::position(),
        );

        $result = $controller($editor, $params);

        $this->assertInstanceOf(SignatureHelp::class, $result);
        $this->assertSame([], $result->signatures);
    }

    #[TestDox('aggregates results from contributors')]
    public function testAggregatesContributors(): void
    {
        $contributor = new class implements \App\Core\Contracts\Signature\SignatureContributor {
            public function contribute(\App\Core\Contracts\Signature\SignatureContext $context, \App\Core\Contracts\Signature\SignatureConsumer $consumer): void
            {
                $consumer(ProtocolFactory::signatureInformation());
            }
        };

        $controller = new SignatureHelpController([$contributor], new NoopTracer());
        $editor = $this->createMock(EditorInterface::class);
        $params = new SignatureHelpParams(
            textDocument: ProtocolFactory::textDocumentIdentifier(),
            position: ProtocolFactory::position(),
        );

        $result = $controller($editor, $params);

        $this->assertCount(1, $result->signatures);
    }
}

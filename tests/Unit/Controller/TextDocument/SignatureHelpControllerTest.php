<?php

declare(strict_types=1);

namespace App\Tests\Unit\Controller\TextDocument;

use App\Controller\TextDocument\SignatureHelpController;
use App\Tests\Support\ProtocolFactory;
use App\Tests\TestCase;
use Lsp\Extension\DocumentManager\Editor\EditorInterface;
use Lsp\Protocol\Type\SignatureHelp;
use Lsp\Protocol\Type\SignatureHelpParams;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\TestDox;

#[Group('unit')]
final class SignatureHelpControllerTest extends TestCase
{
    #[TestDox('returns signature help with empty signatures when no contributors')]
    public function testEmptyContributors(): void
    {
        $controller = new SignatureHelpController([]);
        $editor = $this->createMock(EditorInterface::class);
        $params = new SignatureHelpParams(
            textDocument: ProtocolFactory::textDocumentIdentifier(),
            position: ProtocolFactory::position(),
        );

        $result = $controller($editor, $params);

        $this->assertInstanceOf(SignatureHelp::class, $result);
        $this->assertSame([], $result->signatures);
    }
}

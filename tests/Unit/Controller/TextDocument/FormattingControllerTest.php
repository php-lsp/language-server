<?php

declare(strict_types=1);

namespace App\Tests\Unit\Controller\TextDocument;

use App\Controller\TextDocument\FormattingController;
use App\Tests\Support\MockHelper;
use App\Tests\Support\ProtocolFactory;
use App\Tests\Support\PsiFileFactory;
use App\Tests\TestCase;
use Lsp\Extension\DocumentManager\Editor\EditorInterface;
use Lsp\Protocol\Type\DocumentFormattingParams;
use Lsp\Protocol\Type\FormattingOptions;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\TestDox;
use Psr\Log\LoggerInterface;

#[Group('unit')]
final class FormattingControllerTest extends TestCase
{
    #[TestDox('returns null when document not found')]
    public function testReturnsNullWhenDocumentNotFound(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $controller = new FormattingController($logger);
        $editor = MockHelper::mock(EditorInterface::class);
        $editor->method('findByUriString')->willReturn(null);
        $params = new DocumentFormattingParams(
            textDocument: ProtocolFactory::textDocumentIdentifier(),
            options: new FormattingOptions(tabSize: 4, insertSpaces: true),
        );

        $result = $controller($editor, $params);

        $this->assertNull($result);
    }

    #[TestDox('returns null for non-file URIs')]
    public function testReturnsNullForNonFileUri(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $controller = new FormattingController($logger);

        $document = PsiFileFactory::document('<?php echo 1;', 'https://example.com/test.php');

        $editor = MockHelper::mock(EditorInterface::class);
        $editor->method('findByUriString')->willReturn($document);
        $params = new DocumentFormattingParams(
            textDocument: ProtocolFactory::textDocumentIdentifier('https://example.com/test.php'),
            options: new FormattingOptions(tabSize: 4, insertSpaces: true),
        );

        $result = $controller($editor, $params);

        $this->assertNull($result);
    }
}

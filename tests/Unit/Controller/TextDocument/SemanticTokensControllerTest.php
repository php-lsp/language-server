<?php

declare(strict_types=1);

namespace App\Tests\Unit\Controller\TextDocument;

use App\Controller\TextDocument\SemanticTokensController;
use App\Core\Contracts\PsiFile\PsiFileManagerInterface;
use App\Module\SemanticToken\AstSemanticTokenContributor;
use App\Module\Telemetry\TracerInterface;
use App\Tests\Support\MockHelper;
use App\Tests\Support\ProtocolFactory;
use App\Tests\Support\PsiFileFactory;
use App\Tests\TestCase;
use Lsp\Extension\DocumentManager\Editor\EditorInterface;
use Lsp\Protocol\Type\SemanticTokens;
use Lsp\Protocol\Type\SemanticTokensParams;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\TestDox;

#[Group('unit')]
final class SemanticTokensControllerTest extends TestCase
{
    #[TestDox('returns semantic tokens for PHP file')]
    public function testReturnsSemanticTokens(): void
    {
        $psiFile = PsiFileFactory::fromCode('<?php class Foo {}');
        $fileManager = MockHelper::mock(PsiFileManagerInterface::class);
        $fileManager->method('findPsiFile')->willReturn($psiFile);
        $tracer = MockHelper::mock(TracerInterface::class);
        $tracer->method('trace')->willReturnCallback(fn(string $name, callable $fn) => $fn());

        $controller = new SemanticTokensController(
            [new AstSemanticTokenContributor()],
            $fileManager,
            $tracer,
        );
        $editor = MockHelper::mock(EditorInterface::class);
        $params = new SemanticTokensParams(
            textDocument: ProtocolFactory::textDocumentIdentifier(),
        );

        $result = $controller($editor, $params);

        $this->assertInstanceOf(SemanticTokens::class, $result);
        $this->assertNotEmpty($result->data, 'Should have semantic token data');
        // Data should be groups of 5 integers
        $this->assertSame(0, count($result->data) % 5, 'Data should contain groups of 5 integers');
    }

    #[TestDox('returns empty data when file not found')]
    public function testReturnsEmptyWhenNoFile(): void
    {
        $fileManager = MockHelper::mock(PsiFileManagerInterface::class);
        $fileManager->method('findPsiFile')->willReturn(null);
        $tracer = MockHelper::mock(TracerInterface::class);
        $tracer->method('trace')->willReturnCallback(fn(string $name, callable $fn) => $fn());

        $controller = new SemanticTokensController(
            [new AstSemanticTokenContributor()],
            $fileManager,
            $tracer,
        );
        $editor = MockHelper::mock(EditorInterface::class);
        $params = new SemanticTokensParams(
            textDocument: ProtocolFactory::textDocumentIdentifier(),
        );

        $result = $controller($editor, $params);

        $this->assertInstanceOf(SemanticTokens::class, $result);
        $this->assertEmpty($result->data);
    }
}

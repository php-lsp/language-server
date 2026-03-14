<?php

declare(strict_types=1);

namespace App\Tests\Unit\Controller\TextDocument;

use App\Controller\TextDocument\PrepareRenameController;
use App\Tests\Support\MockHelper;
use App\Tests\Support\ProtocolFactory;
use App\Tests\Support\PsiFileFactory;
use App\Tests\TestCase;
use Lsp\Extension\DocumentManager\Editor\EditorInterface;
use Lsp\Protocol\Type\PrepareRenameParams;
use Lsp\Protocol\Type\Range;
use Lsp\Protocol\Type\TextDocumentIdentifier;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\TestDox;

#[Group('unit')]
final class PrepareRenameControllerTest extends TestCase
{
    #[TestDox('returns null when file not found')]
    public function testReturnsNullWhenNoFile(): void
    {
        $fileManager = MockHelper::mock(\App\Module\PsiFile\InMemoryPsiFileManager::class);
        $fileManager->method('findPsiFile')->willReturn(null);

        $controller = new PrepareRenameController($fileManager);
        $editor = MockHelper::mock(EditorInterface::class);
        $params = new PrepareRenameParams(
            textDocument: new TextDocumentIdentifier('file:///test.php'),
            position: ProtocolFactory::position(0, 0),
        );

        $result = $controller($editor, $params);

        $this->assertNull($result);
    }

    #[TestDox('returns range when file is found')]
    public function testReturnsRangeForElement(): void
    {
        $psiFile = PsiFileFactory::fromCode('<?php class Foo {}');

        $fileManager = MockHelper::mock(\App\Module\PsiFile\InMemoryPsiFileManager::class);
        $fileManager->method('findPsiFile')->willReturn($psiFile);

        $controller = new PrepareRenameController($fileManager);
        $editor = MockHelper::mock(EditorInterface::class);
        $params = new PrepareRenameParams(
            textDocument: new TextDocumentIdentifier('file:///test.php'),
            position: ProtocolFactory::position(0, 14),
        );

        $result = $controller($editor, $params);

        $this->assertInstanceOf(Range::class, $result);
    }
}

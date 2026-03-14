<?php

declare(strict_types=1);

namespace App\Tests\Unit\Controller\TextDocument;

use App\Controller\TextDocument\RenameController;
use App\Module\PsiFile\InMemoryPsiFileManager;
use App\Tests\Support\ProtocolFactory;
use App\Tests\TestCase;
use Lsp\Extension\DocumentManager\Editor\EditorInterface;
use Lsp\Protocol\Type\PrepareRenameParams;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\TestDox;

#[Group('unit')]
final class RenameControllerTest extends TestCase
{
    #[TestDox('invokes without error')]
    public function testInvokesWithoutError(): void
    {
        $fileManager = $this->createMock(InMemoryPsiFileManager::class);
        $controller = new RenameController([], $fileManager);
        $editor = $this->createMock(EditorInterface::class);
        $params = new PrepareRenameParams(
            textDocument: ProtocolFactory::textDocumentIdentifier(),
            position: ProtocolFactory::position(),
        );

        $result = $controller($editor, $params);

        $this->assertNull($result);
    }
}

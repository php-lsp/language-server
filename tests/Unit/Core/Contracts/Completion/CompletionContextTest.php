<?php

declare(strict_types=1);

namespace App\Tests\Unit\Core\Contracts\Completion;

use App\Core\Contracts\Completion\CompletionContext;
use App\Module\PsiFile\InMemoryPsiFileManager;
use App\Tests\Support\ProtocolFactory;
use App\Tests\TestCase;
use Lsp\Extension\DocumentManager\Editor\EditorInterface;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\TestDox;

#[Group('unit')]
final class CompletionContextTest extends TestCase
{
    #[TestDox('exposes constructor properties')]
    public function testProperties(): void
    {
        $textDoc = ProtocolFactory::textDocumentIdentifier();
        $position = ProtocolFactory::position(5, 10);
        $editor = $this->createMock(EditorInterface::class);
        $fileManager = $this->createMock(InMemoryPsiFileManager::class);

        $context = new CompletionContext($textDoc, $position, $editor, $fileManager);

        $this->assertSame($textDoc, $context->textDocumentIdentifier);
        $this->assertSame($position, $context->position);
        $this->assertSame($editor, $context->editor);
        $this->assertSame($fileManager, $context->fileManager);
    }

    #[TestDox('currentNode returns null when file not found')]
    public function testCurrentNodeReturnsNull(): void
    {
        $fileManager = $this->createMock(InMemoryPsiFileManager::class);
        $fileManager->method('findPsiFile')->willReturn(null);

        $context = new CompletionContext(
            ProtocolFactory::textDocumentIdentifier(),
            ProtocolFactory::position(),
            $this->createMock(EditorInterface::class),
            $fileManager,
        );

        $this->assertNull($context->currentNode());
    }
}

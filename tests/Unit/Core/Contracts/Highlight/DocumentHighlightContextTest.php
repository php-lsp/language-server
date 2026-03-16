<?php

declare(strict_types=1);

namespace App\Tests\Unit\Core\Contracts\Highlight;

use App\Core\Contracts\Highlight\DocumentHighlightContext;
use App\Tests\Support\MockHelper;
use App\Tests\Support\ProtocolFactory;
use App\Tests\TestCase;
use Lsp\Extension\DocumentManager\Editor\EditorInterface;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\TestDox;

#[Group('unit')]
#[TestDox('DocumentHighlightContext')]
final class DocumentHighlightContextTest extends TestCase
{
    #[TestDox('exposes constructor properties')]
    public function testProperties(): void
    {
        $textDoc = ProtocolFactory::textDocumentIdentifier();
        $position = ProtocolFactory::position(5, 10);
        $editor = $this->createMock(EditorInterface::class);
        $fileManager = MockHelper::mock(\App\Core\Contracts\PsiFile\PsiFileManagerInterface::class);

        $context = new DocumentHighlightContext($textDoc, $position, $editor, $fileManager);

        $this->assertSame($textDoc, $context->textDocumentIdentifier);
        $this->assertSame($position, $context->position);
        $this->assertSame($editor, $context->editor);
        $this->assertSame($fileManager, $context->fileManager);
    }
}

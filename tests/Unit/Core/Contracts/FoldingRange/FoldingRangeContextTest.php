<?php

declare(strict_types=1);

namespace App\Tests\Unit\Core\Contracts\FoldingRange;

use App\Core\Contracts\FoldingRange\FoldingRangeContext;
use App\Core\Contracts\PsiFile\PsiFileManagerInterface;
use App\Tests\Support\MockHelper;
use App\Tests\Support\ProtocolFactory;
use App\Tests\TestCase;
use Lsp\Extension\DocumentManager\Editor\EditorInterface;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\TestDox;

#[Group('unit')]
final class FoldingRangeContextTest extends TestCase
{
    #[TestDox('exposes constructor properties')]
    public function testExposesProperties(): void
    {
        $textDoc = ProtocolFactory::textDocumentIdentifier();
        $editor = MockHelper::mock(EditorInterface::class);
        $fileManager = MockHelper::mock(PsiFileManagerInterface::class);

        $context = new FoldingRangeContext($textDoc, $editor, $fileManager);

        $this->assertSame($textDoc, $context->textDocumentIdentifier);
        $this->assertSame($editor, $context->editor);
        $this->assertSame($fileManager, $context->fileManager);
    }
}

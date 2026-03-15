<?php

declare(strict_types=1);

namespace App\Tests\Unit\Core\Contracts\Declaration;

use App\Core\Contracts\Declaration\DeclarationContext;
use App\Tests\Support\ProtocolFactory;
use App\Tests\TestCase;
use Lsp\Extension\DocumentManager\Editor\EditorInterface;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\TestDox;

#[Group('unit')]
final class DeclarationContextTest extends TestCase
{
    #[TestDox('exposes readonly properties')]
    public function testProperties(): void
    {
        $textDoc = ProtocolFactory::textDocumentIdentifier();
        $position = ProtocolFactory::position(1, 2);
        $editor = $this->createMock(EditorInterface::class);

        $context = new DeclarationContext($textDoc, $position, $editor);

        $this->assertSame($textDoc, $context->textDocumentIdentifier);
        $this->assertSame($position, $context->position);
        $this->assertSame($editor, $context->editor);
    }
}

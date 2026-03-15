<?php

declare(strict_types=1);

namespace App\Tests\Unit\Core\Contracts\Definition;

use App\Core\Contracts\Definition\DefinitionContext;
use App\Tests\Support\ProtocolFactory;
use App\Tests\TestCase;
use Lsp\Extension\DocumentManager\Editor\EditorInterface;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\TestDox;

#[Group('unit')]
#[TestDox('DefinitionContext')]
final class DefinitionContextTest extends TestCase
{
    #[TestDox('exposes constructor properties')]
    public function testProperties(): void
    {
        $textDoc = ProtocolFactory::textDocumentIdentifier();
        $position = ProtocolFactory::position(5, 10);
        $editor = $this->createMock(EditorInterface::class);

        $context = new DefinitionContext($textDoc, $position, $editor);

        $this->assertSame($textDoc, $context->textDocumentIdentifier);
        $this->assertSame($position, $context->position);
        $this->assertSame($editor, $context->editor);
    }
}

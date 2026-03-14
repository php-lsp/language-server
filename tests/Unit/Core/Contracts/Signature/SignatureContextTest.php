<?php

declare(strict_types=1);

namespace App\Tests\Unit\Core\Contracts\Signature;

use App\Core\Contracts\Signature\SignatureContext;
use App\Tests\Support\ProtocolFactory;
use App\Tests\TestCase;
use Lsp\Extension\DocumentManager\Editor\EditorInterface;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\TestDox;

#[Group('unit')]
final class SignatureContextTest extends TestCase
{
    #[TestDox('exposes readonly properties')]
    public function testProperties(): void
    {
        $textDoc = ProtocolFactory::textDocumentIdentifier();
        $position = ProtocolFactory::position();
        $editor = $this->createMock(EditorInterface::class);

        $context = new SignatureContext($textDoc, $position, $editor);

        $this->assertSame($textDoc, $context->textDocumentIdentifier);
        $this->assertSame($position, $context->position);
        $this->assertSame($editor, $context->editor);
    }
}

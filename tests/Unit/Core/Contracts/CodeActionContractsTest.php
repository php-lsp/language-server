<?php

declare(strict_types=1);

namespace App\Tests\Unit\Core\Contracts;

use App\Core\Contracts\CodeAction\AsCodeActionContributor;
use App\Core\Contracts\CodeAction\CodeActionConsumer;
use App\Core\Contracts\CodeAction\CodeActionContext;
use App\Tests\Support\MockHelper;
use App\Tests\Support\ProtocolFactory;
use App\Tests\TestCase;
use Lsp\Protocol\Type\CodeAction;
use Lsp\Protocol\Type\CodeActionContext as LspCodeActionContext;
use Lsp\Protocol\Type\CodeActionKind;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\TestDox;

#[Group('unit')]
final class CodeActionContractsTest extends TestCase
{
    #[TestDox('consumer accumulates code actions')]
    public function testConsumerAccumulatesCodeActions(): void
    {
        $consumer = new CodeActionConsumer();
        $this->assertEmpty($consumer->results);

        $action = new CodeAction(title: 'Test', kind: CodeActionKind::QuickFix);
        $consumer($action);

        $this->assertCount(1, $consumer->results);
        $this->assertSame('Test', $consumer->results[0]->title);
    }

    #[TestDox('context holds correct properties')]
    public function testContextHoldsProperties(): void
    {
        $editor = MockHelper::mock(\Lsp\Extension\DocumentManager\Editor\EditorInterface::class);
        $textDoc = ProtocolFactory::textDocumentIdentifier();
        $range = ProtocolFactory::range();
        $lspContext = new LspCodeActionContext();

        $context = new CodeActionContext($textDoc, $range, $lspContext, $editor);

        $this->assertSame($textDoc, $context->textDocumentIdentifier);
        $this->assertSame($range, $context->range);
        $this->assertSame($lspContext, $context->lspContext);
    }

    #[TestDox('attribute is valid PHP attribute')]
    public function testAttributeIsValid(): void
    {
        $ref = new \ReflectionClass(AsCodeActionContributor::class);
        $attrs = $ref->getAttributes(\Attribute::class);

        $this->assertCount(1, $attrs);
    }
}

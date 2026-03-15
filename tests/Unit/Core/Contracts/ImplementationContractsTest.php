<?php

declare(strict_types=1);

namespace App\Tests\Unit\Core\Contracts;

use App\Core\Contracts\Implementation\AsImplementationContributor;
use App\Core\Contracts\Implementation\ImplementationConsumer;
use App\Core\Contracts\Implementation\ImplementationContext;
use App\Tests\Support\MockHelper;
use App\Tests\Support\ProtocolFactory;
use App\Tests\TestCase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\TestDox;

#[Group('unit')]
final class ImplementationContractsTest extends TestCase
{
    #[TestDox('consumer accumulates locations')]
    public function testConsumerAccumulatesLocations(): void
    {
        $consumer = new ImplementationConsumer();
        $this->assertEmpty($consumer->results);

        $location = ProtocolFactory::location('file:///impl.php');
        $consumer($location);

        $this->assertCount(1, $consumer->results);
    }

    #[TestDox('context holds correct properties')]
    public function testContextHoldsProperties(): void
    {
        $editor = MockHelper::mock(\Lsp\Extension\DocumentManager\Editor\EditorInterface::class);
        $textDoc = ProtocolFactory::textDocumentIdentifier();
        $position = ProtocolFactory::position(3, 5);

        $context = new ImplementationContext($textDoc, $position, $editor);

        $this->assertSame($textDoc, $context->textDocumentIdentifier);
        $this->assertSame($position, $context->position);
    }

    #[TestDox('attribute is valid PHP attribute')]
    public function testAttributeIsValid(): void
    {
        $ref = new \ReflectionClass(AsImplementationContributor::class);
        $attrs = $ref->getAttributes(\Attribute::class);

        $this->assertCount(1, $attrs);
    }
}

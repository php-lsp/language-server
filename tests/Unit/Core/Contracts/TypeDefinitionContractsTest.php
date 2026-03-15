<?php

declare(strict_types=1);

namespace App\Tests\Unit\Core\Contracts;

use App\Core\Contracts\TypeDefinition\AsTypeDefinitionContributor;
use App\Core\Contracts\TypeDefinition\TypeDefinitionConsumer;
use App\Core\Contracts\TypeDefinition\TypeDefinitionContext;
use App\Tests\Support\MockHelper;
use App\Tests\Support\ProtocolFactory;
use App\Tests\TestCase;
use Lsp\Protocol\Type\Location;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\TestDox;

#[Group('unit')]
final class TypeDefinitionContractsTest extends TestCase
{
    #[TestDox('consumer accumulates locations')]
    public function testConsumerAccumulatesLocations(): void
    {
        $consumer = new TypeDefinitionConsumer();
        $this->assertEmpty($consumer->results);

        $location1 = ProtocolFactory::location('file:///a.php');
        $location2 = ProtocolFactory::location('file:///b.php');

        $consumer($location1, $location2);

        $this->assertCount(2, $consumer->results);
        $this->assertSame('file:///a.php', $consumer->results[0]->uri);
        $this->assertSame('file:///b.php', $consumer->results[1]->uri);
    }

    #[TestDox('context holds correct properties')]
    public function testContextHoldsProperties(): void
    {
        $editor = MockHelper::mock(\Lsp\Extension\DocumentManager\Editor\EditorInterface::class);
        $textDoc = ProtocolFactory::textDocumentIdentifier('file:///test.php');
        $position = ProtocolFactory::position(5, 10);

        $context = new TypeDefinitionContext($textDoc, $position, $editor);

        $this->assertSame($textDoc, $context->textDocumentIdentifier);
        $this->assertSame($position, $context->position);
        $this->assertSame($editor, $context->editor);
    }

    #[TestDox('attribute is valid PHP attribute')]
    public function testAttributeIsValid(): void
    {
        $ref = new \ReflectionClass(AsTypeDefinitionContributor::class);
        $attrs = $ref->getAttributes(\Attribute::class);

        $this->assertCount(1, $attrs);
    }
}

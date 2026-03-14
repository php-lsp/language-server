<?php

declare(strict_types=1);

namespace App\Tests\Unit\Module\Documentation;

use App\Core\Contracts\Documentation\DocumentationConsumer;
use App\Core\Contracts\Documentation\DocumentationContext;
use App\Module\Documentation\DocblockDocumentationContributor;
use App\Module\TypeSystem\TypeResolverInterface;
use App\Module\TypeSystem\TypeResult;
use App\Tests\Support\MockHelper;
use App\Tests\Support\ProtocolFactory;
use App\Tests\TestCase;
use Lsp\Extension\DocumentManager\Editor\EditorInterface;
use PHPStan\Analyser\Scope;
use PHPStan\Type\StringType;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\TestDox;

#[Group('unit')]
final class DocblockDocumentationContributorTest extends TestCase
{
    #[TestDox('contributes type information when type resolved')]
    public function testContributesTypeInfo(): void
    {
        $typeResult = new TypeResult(new StringType(), $this->createMock(Scope::class));

        $typeResolver = MockHelper::mock(TypeResolverInterface::class);
        $typeResolver->method('resolveAtPosition')->willReturn($typeResult);

        $context = new DocumentationContext(
            ProtocolFactory::textDocumentIdentifier(),
            ProtocolFactory::position(0, 5),
            MockHelper::mock(EditorInterface::class),
        );
        $consumer = new DocumentationConsumer();

        $contributor = new DocblockDocumentationContributor($typeResolver);
        $contributor->contribute($context, $consumer);

        $this->assertNotEmpty($consumer->results);
        $this->assertStringContainsString('string', $consumer->results[0]);
    }

    #[TestDox('does nothing when type not resolved')]
    public function testDoesNothingWhenNoType(): void
    {
        $typeResolver = MockHelper::mock(TypeResolverInterface::class);
        $typeResolver->method('resolveAtPosition')->willReturn(null);

        $context = new DocumentationContext(
            ProtocolFactory::textDocumentIdentifier(),
            ProtocolFactory::position(),
            MockHelper::mock(EditorInterface::class),
        );
        $consumer = new DocumentationConsumer();

        $contributor = new DocblockDocumentationContributor($typeResolver);
        $contributor->contribute($context, $consumer);

        $this->assertEmpty($consumer->results);
    }

    #[TestDox('formats output with code block and description')]
    public function testFormatsOutputWithCodeBlock(): void
    {
        $typeResult = new TypeResult(new StringType(), $this->createMock(Scope::class));

        $typeResolver = MockHelper::mock(TypeResolverInterface::class);
        $typeResolver->method('resolveAtPosition')->willReturn($typeResult);

        $context = new DocumentationContext(
            ProtocolFactory::textDocumentIdentifier(),
            ProtocolFactory::position(0, 5),
            MockHelper::mock(EditorInterface::class),
        );
        $consumer = new DocumentationConsumer();

        $contributor = new DocblockDocumentationContributor($typeResolver);
        $contributor->contribute($context, $consumer);

        $output = $consumer->results[0];
        $this->assertStringContainsString('```php', $output);
        $this->assertStringContainsString('```', $output);
    }
}

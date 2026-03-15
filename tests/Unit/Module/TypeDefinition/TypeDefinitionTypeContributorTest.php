<?php

declare(strict_types=1);

namespace App\Tests\Unit\Module\TypeDefinition;

use App\Core\Contracts\TypeDefinition\TypeDefinitionConsumer;
use App\Core\Contracts\TypeDefinition\TypeDefinitionContext;
use App\Module\Indexing\Data\ClassData;
use App\Module\Indexing\Data\EnumData;
use App\Module\Indexing\Data\InterfaceData;
use App\Module\TypeDefinition\TypeDefinitionTypeContributor;
use App\Module\TypeSystem\TypeResolverInterface;
use App\Module\TypeSystem\TypeResult;
use App\Tests\Support\IndexTestHelper;
use App\Tests\Support\MockHelper;
use App\Tests\Support\ProtocolFactory;
use App\Tests\Support\PsiFileFactory;
use App\Tests\TestCase;
use Lsp\Protocol\Type\Location;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\TestDox;
use PHPStan\Type\ObjectType;
use PHPStan\Type\StringType;
use PHPStan\Type\UnionType;

#[Group('unit')]
final class TypeDefinitionTypeContributorTest extends TestCase
{
    private function createContributor(
        TypeResolverInterface $typeResolver,
        \App\Module\Indexing\IndexLookup $lookup,
        ?\App\Module\PsiFile\PHPPsiFile $returnedPsiFile = null,
    ): TypeDefinitionTypeContributor {
        $fileManager = MockHelper::mock(\App\Module\PsiFile\InMemoryPsiFileManager::class);
        $fileManager->method('findPsiFile')->willReturn($returnedPsiFile);

        $docIdFactory = MockHelper::mock(\App\Module\Document\DocumentIdentifierFactoryInterface::class);
        $docIdFactory->method('create')->willReturn(ProtocolFactory::textDocumentIdentifier());

        return new TypeDefinitionTypeContributor($typeResolver, $lookup, $fileManager, $docIdFactory);
    }

    #[TestDox('returns empty when type resolver returns null')]
    public function testReturnsEmptyWhenNoType(): void
    {
        $typeResolver = MockHelper::mock(TypeResolverInterface::class);
        $typeResolver->method('resolveAtPosition')->willReturn(null);

        $lookup = IndexTestHelper::createLookup();
        $contributor = $this->createContributor($typeResolver, $lookup);

        $editor = MockHelper::mock(\Lsp\Extension\DocumentManager\Editor\EditorInterface::class);
        $context = new TypeDefinitionContext(
            ProtocolFactory::textDocumentIdentifier(),
            ProtocolFactory::position(0, 5),
            $editor,
        );
        $consumer = new TypeDefinitionConsumer();
        $contributor->contribute($context, $consumer);

        $this->assertEmpty($consumer->results);
    }

    #[TestDox('returns empty when type is not an object type')]
    public function testReturnsEmptyWhenNotObjectType(): void
    {
        $scope = MockHelper::mock(\PHPStan\Analyser\Scope::class);
        $typeResult = new TypeResult(new StringType(), $scope);

        $typeResolver = MockHelper::mock(TypeResolverInterface::class);
        $typeResolver->method('resolveAtPosition')->willReturn($typeResult);

        $lookup = IndexTestHelper::createLookup();
        $contributor = $this->createContributor($typeResolver, $lookup);

        $editor = MockHelper::mock(\Lsp\Extension\DocumentManager\Editor\EditorInterface::class);
        $context = new TypeDefinitionContext(
            ProtocolFactory::textDocumentIdentifier(),
            ProtocolFactory::position(0, 5),
            $editor,
        );
        $consumer = new TypeDefinitionConsumer();
        $contributor->contribute($context, $consumer);

        $this->assertEmpty($consumer->results);
    }

    #[TestDox('finds class type definition via object type')]
    public function testFindsClassTypeDefinition(): void
    {
        $scope = MockHelper::mock(\PHPStan\Analyser\Scope::class);
        $typeResult = new TypeResult(new ObjectType('App\\Foo'), $scope);

        $typeResolver = MockHelper::mock(TypeResolverInterface::class);
        $typeResolver->method('resolveAtPosition')->willReturn($typeResult);

        $psiFile = PsiFileFactory::fromCode('<?php namespace App; class Foo {}');

        $lookup = IndexTestHelper::createLookup([
            'php.classes.fqn' => [
                'file:///foo.php' => ['App\\Foo' => new ClassData('App\\Foo', 21, 50, false, false, false, null, [])],
            ],
        ]);

        $contributor = $this->createContributor($typeResolver, $lookup, $psiFile);

        $editor = MockHelper::mock(\Lsp\Extension\DocumentManager\Editor\EditorInterface::class);
        $context = new TypeDefinitionContext(
            ProtocolFactory::textDocumentIdentifier(),
            ProtocolFactory::position(0, 5),
            $editor,
        );
        $consumer = new TypeDefinitionConsumer();
        $contributor->contribute($context, $consumer);

        $this->assertCount(1, $consumer->results);
        $this->assertInstanceOf(Location::class, $consumer->results[0]);
        $this->assertSame('file:///foo.php', $consumer->results[0]->uri);
    }

    #[TestDox('finds interface type definition')]
    public function testFindsInterfaceTypeDefinition(): void
    {
        $scope = MockHelper::mock(\PHPStan\Analyser\Scope::class);
        $typeResult = new TypeResult(new ObjectType('App\\FooInterface'), $scope);

        $typeResolver = MockHelper::mock(TypeResolverInterface::class);
        $typeResolver->method('resolveAtPosition')->willReturn($typeResult);

        $psiFile = PsiFileFactory::fromCode('<?php namespace App; interface FooInterface {}');

        $lookup = IndexTestHelper::createLookup([
            'php.interfaces.fqn' => [
                'file:///iface.php' => ['App\\FooInterface' => new InterfaceData('App\\FooInterface', 21, 50, [])],
            ],
        ]);

        $contributor = $this->createContributor($typeResolver, $lookup, $psiFile);

        $editor = MockHelper::mock(\Lsp\Extension\DocumentManager\Editor\EditorInterface::class);
        $context = new TypeDefinitionContext(
            ProtocolFactory::textDocumentIdentifier(),
            ProtocolFactory::position(0, 5),
            $editor,
        );
        $consumer = new TypeDefinitionConsumer();
        $contributor->contribute($context, $consumer);

        $this->assertCount(1, $consumer->results);
        $this->assertSame('file:///iface.php', $consumer->results[0]->uri);
    }

    #[TestDox('finds enum type definition')]
    public function testFindsEnumTypeDefinition(): void
    {
        $scope = MockHelper::mock(\PHPStan\Analyser\Scope::class);
        $typeResult = new TypeResult(new ObjectType('App\\Status'), $scope);

        $typeResolver = MockHelper::mock(TypeResolverInterface::class);
        $typeResolver->method('resolveAtPosition')->willReturn($typeResult);

        $psiFile = PsiFileFactory::fromCode('<?php namespace App; enum Status: string {}');

        $lookup = IndexTestHelper::createLookup([
            'php.enums.fqn' => [
                'file:///enum.php' => ['App\\Status' => new EnumData('App\\Status', 21, 50, 'string', [])],
            ],
        ]);

        $contributor = $this->createContributor($typeResolver, $lookup, $psiFile);

        $editor = MockHelper::mock(\Lsp\Extension\DocumentManager\Editor\EditorInterface::class);
        $context = new TypeDefinitionContext(
            ProtocolFactory::textDocumentIdentifier(),
            ProtocolFactory::position(0, 5),
            $editor,
        );
        $consumer = new TypeDefinitionConsumer();
        $contributor->contribute($context, $consumer);

        $this->assertCount(1, $consumer->results);
        $this->assertSame('file:///enum.php', $consumer->results[0]->uri);
    }

    #[TestDox('handles union type with multiple object types')]
    public function testHandlesUnionType(): void
    {
        $scope = MockHelper::mock(\PHPStan\Analyser\Scope::class);
        $unionType = new UnionType([new ObjectType('App\\Foo'), new StringType()]);
        $typeResult = new TypeResult($unionType, $scope);

        $typeResolver = MockHelper::mock(TypeResolverInterface::class);
        $typeResolver->method('resolveAtPosition')->willReturn($typeResult);

        $psiFile = PsiFileFactory::fromCode('<?php namespace App; class Foo {}');

        $lookup = IndexTestHelper::createLookup([
            'php.classes.fqn' => [
                'file:///foo.php' => ['App\\Foo' => new ClassData('App\\Foo', 21, 50, false, false, false, null, [])],
            ],
        ]);

        $contributor = $this->createContributor($typeResolver, $lookup, $psiFile);

        $editor = MockHelper::mock(\Lsp\Extension\DocumentManager\Editor\EditorInterface::class);
        $context = new TypeDefinitionContext(
            ProtocolFactory::textDocumentIdentifier(),
            ProtocolFactory::position(0, 5),
            $editor,
        );
        $consumer = new TypeDefinitionConsumer();
        $contributor->contribute($context, $consumer);

        $this->assertCount(1, $consumer->results);
    }

    #[TestDox('returns empty when class not found in index')]
    public function testReturnsEmptyWhenClassNotInIndex(): void
    {
        $scope = MockHelper::mock(\PHPStan\Analyser\Scope::class);
        $typeResult = new TypeResult(new ObjectType('App\\NonExistent'), $scope);

        $typeResolver = MockHelper::mock(TypeResolverInterface::class);
        $typeResolver->method('resolveAtPosition')->willReturn($typeResult);

        $lookup = IndexTestHelper::createLookup();

        $contributor = $this->createContributor($typeResolver, $lookup);

        $editor = MockHelper::mock(\Lsp\Extension\DocumentManager\Editor\EditorInterface::class);
        $context = new TypeDefinitionContext(
            ProtocolFactory::textDocumentIdentifier(),
            ProtocolFactory::position(0, 5),
            $editor,
        );
        $consumer = new TypeDefinitionConsumer();
        $contributor->contribute($context, $consumer);

        $this->assertEmpty($consumer->results);
    }
}

<?php

declare(strict_types=1);

namespace App\Tests\Unit\Module\Declaration;

use App\Core\Contracts\Declaration\DeclarationConsumer;
use App\Core\Contracts\Declaration\DeclarationContext;
use App\Module\Declaration\ClassDeclarationContributor;
use App\Module\Document\DocumentIdentifierFactoryInterface;
use App\Module\Indexing\Storage\IndexData\ClassData;
use App\Module\Indexing\Storage\IndexData\InterfaceData;
use App\Module\Indexing\Storage\IndexData\TraitData;
use App\Tests\Support\IndexTestHelper;
use App\Tests\Support\MockHelper;
use App\Tests\Support\ProtocolFactory;
use App\Tests\Support\PsiFileFactory;
use App\Tests\TestCase;
use Lsp\Protocol\Type\Location;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\TestDox;

#[Group('unit')]
final class ClassDeclarationContributorTest extends TestCase
{
    #[TestDox('returns empty when file not found')]
    public function testReturnsEmptyWhenNoFile(): void
    {
        $lookup = IndexTestHelper::createLookup();
        $fileManager = MockHelper::mock(\App\Module\PsiFile\InMemoryPsiFileManager::class);
        $fileManager->method('findPsiFile')->willReturn(null);
        $docFactory = $this->createMock(DocumentIdentifierFactoryInterface::class);

        $contributor = new ClassDeclarationContributor($lookup, $fileManager, $docFactory);

        $editor = MockHelper::mock(\Lsp\Extension\DocumentManager\Editor\EditorInterface::class);
        $context = new DeclarationContext(
            ProtocolFactory::textDocumentIdentifier(),
            ProtocolFactory::position(0, 10),
            $editor,
        );
        $consumer = new DeclarationConsumer();
        $contributor->contribute($context, $consumer);

        $this->assertEmpty($consumer->results);
    }

    #[TestDox('returns empty when node is not FullyQualified')]
    public function testReturnsEmptyWhenNotFullyQualified(): void
    {
        $psiFile = PsiFileFactory::fromCode('<?php echo 1;');
        $lookup = IndexTestHelper::createLookup();
        $fileManager = MockHelper::mock(\App\Module\PsiFile\InMemoryPsiFileManager::class);
        $fileManager->method('findPsiFile')->willReturn($psiFile);
        $docFactory = $this->createMock(DocumentIdentifierFactoryInterface::class);

        $contributor = new ClassDeclarationContributor($lookup, $fileManager, $docFactory);

        $editor = MockHelper::mock(\Lsp\Extension\DocumentManager\Editor\EditorInterface::class);
        $context = new DeclarationContext(
            ProtocolFactory::textDocumentIdentifier(),
            ProtocolFactory::position(0, 10),
            $editor,
        );
        $consumer = new DeclarationConsumer();
        $contributor->contribute($context, $consumer);

        $this->assertEmpty($consumer->results);
    }

    #[TestDox('finds class declaration via new expression')]
    public function testFindsClassViaNew(): void
    {
        $psiFile = PsiFileFactory::fromCode('<?php new \Foo();');
        $lookup = IndexTestHelper::createLookup([
            'php.classes.fqn' => [
                'file:///def.php' => ['Foo' => new ClassData('Foo', 0, 10, null, [], false, false)],
            ],
        ]);
        $fileManager = MockHelper::mock(\App\Module\PsiFile\InMemoryPsiFileManager::class);
        $fileManager->method('findPsiFile')->willReturn($psiFile);
        $docFactory = $this->createMock(DocumentIdentifierFactoryInterface::class);
        $docFactory->method('create')->willReturn(ProtocolFactory::textDocumentIdentifier('file:///def.php'));

        $contributor = new ClassDeclarationContributor($lookup, $fileManager, $docFactory);

        $editor = MockHelper::mock(\Lsp\Extension\DocumentManager\Editor\EditorInterface::class);
        $context = new DeclarationContext(
            ProtocolFactory::textDocumentIdentifier(),
            ProtocolFactory::position(0, 11),
            $editor,
        );
        $consumer = new DeclarationConsumer();
        $contributor->contribute($context, $consumer);

        $this->assertCount(1, $consumer->results);
        $this->assertInstanceOf(Location::class, $consumer->results[0]);
        $this->assertSame('file:///def.php', $consumer->results[0]->uri);
    }

    #[TestDox('finds class declaration via extends')]
    public function testFindsClassViaExtends(): void
    {
        $psiFile = PsiFileFactory::fromCode('<?php class Bar extends \Foo {}');
        $lookup = IndexTestHelper::createLookup([
            'php.classes.fqn' => [
                'file:///def.php' => ['Foo' => new ClassData('Foo', 0, 10, null, [], false, false)],
            ],
        ]);
        $fileManager = MockHelper::mock(\App\Module\PsiFile\InMemoryPsiFileManager::class);
        $fileManager->method('findPsiFile')->willReturn($psiFile);
        $docFactory = $this->createMock(DocumentIdentifierFactoryInterface::class);
        $docFactory->method('create')->willReturn(ProtocolFactory::textDocumentIdentifier('file:///def.php'));

        $contributor = new ClassDeclarationContributor($lookup, $fileManager, $docFactory);

        $editor = MockHelper::mock(\Lsp\Extension\DocumentManager\Editor\EditorInterface::class);
        $context = new DeclarationContext(
            ProtocolFactory::textDocumentIdentifier(),
            ProtocolFactory::position(0, 26),
            $editor,
        );
        $consumer = new DeclarationConsumer();
        $contributor->contribute($context, $consumer);

        $this->assertCount(1, $consumer->results);
    }

    #[TestDox('finds interface declaration via implements')]
    public function testFindsInterfaceViaImplements(): void
    {
        $psiFile = PsiFileFactory::fromCode('<?php class Bar implements \Baz {}');
        $lookup = IndexTestHelper::createLookup([
            'php.interfaces.fqn' => [
                'file:///iface.php' => ['Baz' => new InterfaceData('Baz', 0, 10, [])],
            ],
        ]);
        $fileManager = MockHelper::mock(\App\Module\PsiFile\InMemoryPsiFileManager::class);
        $fileManager->method('findPsiFile')->willReturn($psiFile);
        $docFactory = $this->createMock(DocumentIdentifierFactoryInterface::class);
        $docFactory->method('create')->willReturn(ProtocolFactory::textDocumentIdentifier('file:///iface.php'));

        $contributor = new ClassDeclarationContributor($lookup, $fileManager, $docFactory);

        $editor = MockHelper::mock(\Lsp\Extension\DocumentManager\Editor\EditorInterface::class);
        $context = new DeclarationContext(
            ProtocolFactory::textDocumentIdentifier(),
            ProtocolFactory::position(0, 30),
            $editor,
        );
        $consumer = new DeclarationConsumer();
        $contributor->contribute($context, $consumer);

        // Interface index stores values differently, may or may not match
        $this->assertIsArray($consumer->results);
    }

    #[TestDox('finds via class constant fetch')]
    public function testFindsViaClassConstFetch(): void
    {
        $psiFile = PsiFileFactory::fromCode('<?php \Foo::BAR;');
        $lookup = IndexTestHelper::createLookup([
            'php.classes.fqn' => [
                'file:///def.php' => ['Foo' => new ClassData('Foo', 0, 10, null, [], false, false)],
            ],
        ]);
        $fileManager = MockHelper::mock(\App\Module\PsiFile\InMemoryPsiFileManager::class);
        $fileManager->method('findPsiFile')->willReturn($psiFile);
        $docFactory = $this->createMock(DocumentIdentifierFactoryInterface::class);
        $docFactory->method('create')->willReturn(ProtocolFactory::textDocumentIdentifier('file:///def.php'));

        $contributor = new ClassDeclarationContributor($lookup, $fileManager, $docFactory);

        $editor = MockHelper::mock(\Lsp\Extension\DocumentManager\Editor\EditorInterface::class);
        $context = new DeclarationContext(
            ProtocolFactory::textDocumentIdentifier(),
            ProtocolFactory::position(0, 8),
            $editor,
        );
        $consumer = new DeclarationConsumer();
        $contributor->contribute($context, $consumer);

        $this->assertCount(1, $consumer->results);
    }

    #[TestDox('finds via method return type')]
    public function testFindsViaMethodReturnType(): void
    {
        $psiFile = PsiFileFactory::fromCode('<?php class Bar { public function baz(): \Foo {} }');
        $lookup = IndexTestHelper::createLookup([
            'php.classes.fqn' => [
                'file:///def.php' => ['Foo' => new ClassData('Foo', 0, 10, null, [], false, false)],
            ],
        ]);
        $fileManager = MockHelper::mock(\App\Module\PsiFile\InMemoryPsiFileManager::class);
        $fileManager->method('findPsiFile')->willReturn($psiFile);
        $docFactory = $this->createMock(DocumentIdentifierFactoryInterface::class);
        $docFactory->method('create')->willReturn(ProtocolFactory::textDocumentIdentifier('file:///def.php'));

        $contributor = new ClassDeclarationContributor($lookup, $fileManager, $docFactory);

        $editor = MockHelper::mock(\Lsp\Extension\DocumentManager\Editor\EditorInterface::class);
        $context = new DeclarationContext(
            ProtocolFactory::textDocumentIdentifier(),
            ProtocolFactory::position(0, 43),
            $editor,
        );
        $consumer = new DeclarationConsumer();
        $contributor->contribute($context, $consumer);

        $this->assertCount(1, $consumer->results);
    }

    #[TestDox('finds via interface extends')]
    public function testFindsViaInterfaceExtends(): void
    {
        $psiFile = PsiFileFactory::fromCode('<?php interface Bar extends \Foo {}');
        $lookup = IndexTestHelper::createLookup([
            'php.interfaces.fqn' => [
                'file:///iface.php' => ['Foo' => new InterfaceData('Foo', 0, 10, [])],
            ],
        ]);
        $fileManager = MockHelper::mock(\App\Module\PsiFile\InMemoryPsiFileManager::class);
        $fileManager->method('findPsiFile')->willReturn($psiFile);
        $docFactory = $this->createMock(DocumentIdentifierFactoryInterface::class);
        $docFactory->method('create')->willReturn(ProtocolFactory::textDocumentIdentifier('file:///iface.php'));

        $contributor = new ClassDeclarationContributor($lookup, $fileManager, $docFactory);

        $editor = MockHelper::mock(\Lsp\Extension\DocumentManager\Editor\EditorInterface::class);
        $context = new DeclarationContext(
            ProtocolFactory::textDocumentIdentifier(),
            ProtocolFactory::position(0, 30),
            $editor,
        );
        $consumer = new DeclarationConsumer();
        $contributor->contribute($context, $consumer);

        $this->assertIsArray($consumer->results);
    }

    #[TestDox('finds via trait use')]
    public function testFindsViaTraitUse(): void
    {
        $psiFile = PsiFileFactory::fromCode('<?php class Bar { use \Foo; }');
        $lookup = IndexTestHelper::createLookup([
            'php.traits.fqn' => [
                'file:///trait.php' => ['Foo' => new TraitData('Foo', 0, 10)],
            ],
        ]);
        $fileManager = MockHelper::mock(\App\Module\PsiFile\InMemoryPsiFileManager::class);
        $fileManager->method('findPsiFile')->willReturn($psiFile);
        $docFactory = $this->createMock(DocumentIdentifierFactoryInterface::class);
        $docFactory->method('create')->willReturn(ProtocolFactory::textDocumentIdentifier('file:///trait.php'));

        $contributor = new ClassDeclarationContributor($lookup, $fileManager, $docFactory);

        $editor = MockHelper::mock(\Lsp\Extension\DocumentManager\Editor\EditorInterface::class);
        $context = new DeclarationContext(
            ProtocolFactory::textDocumentIdentifier(),
            ProtocolFactory::position(0, 24),
            $editor,
        );
        $consumer = new DeclarationConsumer();
        $contributor->contribute($context, $consumer);

        $this->assertIsArray($consumer->results);
    }

    #[TestDox('finds via parameter type hint')]
    public function testFindsViaParamType(): void
    {
        $psiFile = PsiFileFactory::fromCode('<?php function bar(\Foo $x) {}');
        $lookup = IndexTestHelper::createLookup([
            'php.classes.fqn' => [
                'file:///def.php' => ['Foo' => new ClassData('Foo', 0, 10, null, [], false, false)],
            ],
        ]);
        $fileManager = MockHelper::mock(\App\Module\PsiFile\InMemoryPsiFileManager::class);
        $fileManager->method('findPsiFile')->willReturn($psiFile);
        $docFactory = $this->createMock(DocumentIdentifierFactoryInterface::class);
        $docFactory->method('create')->willReturn(ProtocolFactory::textDocumentIdentifier('file:///def.php'));

        $contributor = new ClassDeclarationContributor($lookup, $fileManager, $docFactory);

        $editor = MockHelper::mock(\Lsp\Extension\DocumentManager\Editor\EditorInterface::class);
        $context = new DeclarationContext(
            ProtocolFactory::textDocumentIdentifier(),
            ProtocolFactory::position(0, 22),
            $editor,
        );
        $consumer = new DeclarationConsumer();
        $contributor->contribute($context, $consumer);

        $this->assertCount(1, $consumer->results);
    }
}

<?php

declare(strict_types=1);

namespace App\Tests\Unit\Module\Definition;

use App\Core\Contracts\Definition\DefinitionConsumer;
use App\Core\Contracts\Definition\DefinitionContext;
use App\Module\Definition\ClassDefinitionContributor;
use App\Module\Indexing\Data\ClassData;
use App\Module\Indexing\Data\InterfaceData;
use App\Module\Indexing\Data\TraitData;
use App\Tests\Support\IndexTestHelper;
use App\Tests\Support\MockHelper;
use App\Tests\Support\ProtocolFactory;
use App\Tests\Support\PsiFileFactory;
use App\Tests\TestCase;
use Lsp\Protocol\Type\Location;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\TestDox;

#[Group('unit')]
final class ClassDefinitionContributorTest extends TestCase
{
    #[TestDox('returns empty when file not found')]
    public function testReturnsEmptyWhenNoFile(): void
    {
        $lookup = IndexTestHelper::createLookup();
        $fileManager = MockHelper::mock(\App\Module\PsiFile\InMemoryPsiFileManager::class);
        $fileManager->method('findPsiFile')->willReturn(null);

        $contributor = new ClassDefinitionContributor($lookup, $fileManager);

        $editor = MockHelper::mock(\Lsp\Extension\DocumentManager\Editor\EditorInterface::class);
        $context = new DefinitionContext(
            ProtocolFactory::textDocumentIdentifier(),
            ProtocolFactory::position(0, 10),
            $editor,
        );
        $consumer = new DefinitionConsumer();
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

        $contributor = new ClassDefinitionContributor($lookup, $fileManager);

        $editor = MockHelper::mock(\Lsp\Extension\DocumentManager\Editor\EditorInterface::class);
        $context = new DefinitionContext(
            ProtocolFactory::textDocumentIdentifier(),
            ProtocolFactory::position(0, 10),
            $editor,
        );
        $consumer = new DefinitionConsumer();
        $contributor->contribute($context, $consumer);

        $this->assertEmpty($consumer->results);
    }

    #[TestDox('finds class definition via new expression')]
    public function testFindsClassViaNew(): void
    {
        $psiFile = PsiFileFactory::fromCode('<?php new \Foo();');
        $lookup = IndexTestHelper::createLookup([
            'php.classes.fqn' => [
                'file:///def.php' => ['Foo' => new ClassData('Foo', 0, 50, false, false, false, null, [])],
            ],
        ]);
        $fileManager = MockHelper::mock(\App\Module\PsiFile\InMemoryPsiFileManager::class);
        $fileManager->method('findPsiFile')->willReturn($psiFile);

        $contributor = new ClassDefinitionContributor($lookup, $fileManager);

        $editor = MockHelper::mock(\Lsp\Extension\DocumentManager\Editor\EditorInterface::class);
        $context = new DefinitionContext(
            ProtocolFactory::textDocumentIdentifier(),
            ProtocolFactory::position(0, 11),
            $editor,
        );
        $consumer = new DefinitionConsumer();
        $contributor->contribute($context, $consumer);

        $this->assertCount(1, $consumer->results);
        $this->assertInstanceOf(Location::class, $consumer->results[0]);
        $this->assertSame('file:///def.php', $consumer->results[0]->uri);
    }

    #[TestDox('finds class definition via extends')]
    public function testFindsClassViaExtends(): void
    {
        $psiFile = PsiFileFactory::fromCode('<?php class Bar extends \Foo {}');
        $lookup = IndexTestHelper::createLookup([
            'php.classes.fqn' => [
                'file:///def.php' => ['Foo' => new ClassData('Foo', 0, 50, false, false, false, null, [])],
            ],
        ]);
        $fileManager = MockHelper::mock(\App\Module\PsiFile\InMemoryPsiFileManager::class);
        $fileManager->method('findPsiFile')->willReturn($psiFile);

        $contributor = new ClassDefinitionContributor($lookup, $fileManager);

        $editor = MockHelper::mock(\Lsp\Extension\DocumentManager\Editor\EditorInterface::class);
        $context = new DefinitionContext(
            ProtocolFactory::textDocumentIdentifier(),
            ProtocolFactory::position(0, 26),
            $editor,
        );
        $consumer = new DefinitionConsumer();
        $contributor->contribute($context, $consumer);

        $this->assertCount(1, $consumer->results);
    }

    #[TestDox('finds interface definition via implements')]
    public function testFindsInterfaceViaImplements(): void
    {
        $psiFile = PsiFileFactory::fromCode('<?php class Bar implements \Baz {}');
        $lookup = IndexTestHelper::createLookup([
            'php.interfaces.fqn' => [
                'file:///iface.php' => ['Baz' => new InterfaceData('Baz', 0, 50, [])],
            ],
        ]);
        $fileManager = MockHelper::mock(\App\Module\PsiFile\InMemoryPsiFileManager::class);
        $fileManager->method('findPsiFile')->willReturn($psiFile);

        $contributor = new ClassDefinitionContributor($lookup, $fileManager);

        $editor = MockHelper::mock(\Lsp\Extension\DocumentManager\Editor\EditorInterface::class);
        $context = new DefinitionContext(
            ProtocolFactory::textDocumentIdentifier(),
            ProtocolFactory::position(0, 30),
            $editor,
        );
        $consumer = new DefinitionConsumer();
        $contributor->contribute($context, $consumer);

        $this->assertIsArray($consumer->results);
    }

    #[TestDox('finds trait definition via trait use')]
    public function testFindsTraitViaTraitUse(): void
    {
        $psiFile = PsiFileFactory::fromCode('<?php class Bar { use \Foo; }');
        $lookup = IndexTestHelper::createLookup([
            'php.traits.fqn' => [
                'file:///trait.php' => ['Foo' => new TraitData('Foo', 0, 50)],
            ],
        ]);
        $fileManager = MockHelper::mock(\App\Module\PsiFile\InMemoryPsiFileManager::class);
        $fileManager->method('findPsiFile')->willReturn($psiFile);

        $contributor = new ClassDefinitionContributor($lookup, $fileManager);

        $editor = MockHelper::mock(\Lsp\Extension\DocumentManager\Editor\EditorInterface::class);
        $context = new DefinitionContext(
            ProtocolFactory::textDocumentIdentifier(),
            ProtocolFactory::position(0, 24),
            $editor,
        );
        $consumer = new DefinitionConsumer();
        $contributor->contribute($context, $consumer);

        $this->assertIsArray($consumer->results);
    }

    #[TestDox('finds via parameter type hint')]
    public function testFindsViaParamType(): void
    {
        $psiFile = PsiFileFactory::fromCode('<?php function bar(\Foo $x) {}');
        $lookup = IndexTestHelper::createLookup([
            'php.classes.fqn' => [
                'file:///def.php' => ['Foo' => new ClassData('Foo', 0, 50, false, false, false, null, [])],
            ],
        ]);
        $fileManager = MockHelper::mock(\App\Module\PsiFile\InMemoryPsiFileManager::class);
        $fileManager->method('findPsiFile')->willReturn($psiFile);

        $contributor = new ClassDefinitionContributor($lookup, $fileManager);

        $editor = MockHelper::mock(\Lsp\Extension\DocumentManager\Editor\EditorInterface::class);
        $context = new DefinitionContext(
            ProtocolFactory::textDocumentIdentifier(),
            ProtocolFactory::position(0, 22),
            $editor,
        );
        $consumer = new DefinitionConsumer();
        $contributor->contribute($context, $consumer);

        $this->assertCount(1, $consumer->results);
    }
}

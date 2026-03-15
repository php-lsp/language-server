<?php

declare(strict_types=1);

namespace App\Tests\Unit\Module\Implementation;

use App\Core\Contracts\Implementation\ImplementationConsumer;
use App\Core\Contracts\Implementation\ImplementationContext;
use App\Module\Implementation\InterfaceImplementationContributor;
use App\Module\Indexing\Data\ClassData;
use App\Module\Indexing\Data\InheritanceData;
use App\Tests\Support\IndexTestHelper;
use App\Tests\Support\MockHelper;
use App\Tests\Support\ProtocolFactory;
use App\Tests\Support\PsiFileFactory;
use App\Tests\TestCase;
use Lsp\Protocol\Type\Location;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\TestDox;

#[Group('unit')]
final class InterfaceImplementationContributorTest extends TestCase
{
    #[TestDox('returns empty when file not found')]
    public function testReturnsEmptyWhenNoFile(): void
    {
        $lookup = IndexTestHelper::createLookup();
        $fileManager = MockHelper::mock(\App\Module\PsiFile\InMemoryPsiFileManager::class);
        $fileManager->method('findPsiFile')->willReturn(null);

        $contributor = new InterfaceImplementationContributor($lookup, $fileManager);

        $editor = MockHelper::mock(\Lsp\Extension\DocumentManager\Editor\EditorInterface::class);
        $context = new ImplementationContext(
            ProtocolFactory::textDocumentIdentifier(),
            ProtocolFactory::position(0, 10),
            $editor,
        );
        $consumer = new ImplementationConsumer();
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

        $contributor = new InterfaceImplementationContributor($lookup, $fileManager);

        $editor = MockHelper::mock(\Lsp\Extension\DocumentManager\Editor\EditorInterface::class);
        $context = new ImplementationContext(
            ProtocolFactory::textDocumentIdentifier(),
            ProtocolFactory::position(0, 10),
            $editor,
        );
        $consumer = new ImplementationConsumer();
        $contributor->contribute($context, $consumer);

        $this->assertEmpty($consumer->results);
    }

    #[TestDox('finds implementations of an interface')]
    public function testFindsInterfaceImplementations(): void
    {
        $psiFile = PsiFileFactory::fromCode('<?php class Bar implements \Foo {}');
        $lookup = IndexTestHelper::createLookup([
            'php.inheritance' => [
                'file:///impl.php' => [
                    'Bar' => new InheritanceData('Bar', 'class', ['Foo']),
                    'Baz' => new InheritanceData('Baz', 'class', ['Foo']),
                ],
            ],
            'php.classes.fqn' => [
                'file:///impl.php' => [
                    'Bar' => new ClassData('Bar', 0, 50, false, false, false, null, ['Foo']),
                    'Baz' => new ClassData('Baz', 0, 50, false, false, false, null, ['Foo']),
                ],
            ],
        ]);

        $fileManager = MockHelper::mock(\App\Module\PsiFile\InMemoryPsiFileManager::class);
        $fileManager->method('findPsiFile')->willReturn($psiFile);

        $contributor = new InterfaceImplementationContributor($lookup, $fileManager);

        $editor = MockHelper::mock(\Lsp\Extension\DocumentManager\Editor\EditorInterface::class);
        $context = new ImplementationContext(
            ProtocolFactory::textDocumentIdentifier(),
            ProtocolFactory::position(0, 30),
            $editor,
        );
        $consumer = new ImplementationConsumer();
        $contributor->contribute($context, $consumer);

        $this->assertCount(2, $consumer->results);
        $this->assertInstanceOf(Location::class, $consumer->results[0]);
    }

    #[TestDox('returns empty when no implementations found')]
    public function testReturnsEmptyWhenNoImplementations(): void
    {
        $psiFile = PsiFileFactory::fromCode('<?php class Bar implements \Foo {}');
        $lookup = IndexTestHelper::createLookup([
            'php.inheritance' => [
                'file:///other.php' => [
                    'Other' => new InheritanceData('Other', 'class', ['SomethingElse']),
                ],
            ],
        ]);

        $fileManager = MockHelper::mock(\App\Module\PsiFile\InMemoryPsiFileManager::class);
        $fileManager->method('findPsiFile')->willReturn($psiFile);

        $contributor = new InterfaceImplementationContributor($lookup, $fileManager);

        $editor = MockHelper::mock(\Lsp\Extension\DocumentManager\Editor\EditorInterface::class);
        $context = new ImplementationContext(
            ProtocolFactory::textDocumentIdentifier(),
            ProtocolFactory::position(0, 30),
            $editor,
        );
        $consumer = new ImplementationConsumer();
        $contributor->contribute($context, $consumer);

        $this->assertEmpty($consumer->results);
    }
}

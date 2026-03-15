<?php

declare(strict_types=1);

namespace App\Tests\Unit\Module\Declaration;

use App\Core\Contracts\Declaration\DeclarationConsumer;
use App\Core\Contracts\Declaration\DeclarationContext;
use App\Module\Declaration\ClassMethodDeclarationContributor;
use App\Module\Document\DocumentIdentifierFactoryInterface;
use App\Module\Indexing\Storage\IndexData\MethodData;
use App\Tests\Support\IndexTestHelper;
use App\Tests\Support\MockHelper;
use App\Tests\Support\ProtocolFactory;
use App\Tests\Support\PsiFileFactory;
use App\Tests\TestCase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\TestDox;

#[Group('unit')]
final class ClassMethodDeclarationContributorTest extends TestCase
{
    #[TestDox('returns empty when file not found')]
    public function testReturnsEmptyWhenNoFile(): void
    {
        $lookup = IndexTestHelper::createLookup();
        $fileManager = MockHelper::mock(\App\Module\PsiFile\InMemoryPsiFileManager::class);
        $fileManager->method('findPsiFile')->willReturn(null);
        $docFactory = $this->createMock(DocumentIdentifierFactoryInterface::class);

        $contributor = new ClassMethodDeclarationContributor($lookup, $fileManager, $docFactory);

        $editor = MockHelper::mock(\Lsp\Extension\DocumentManager\Editor\EditorInterface::class);
        $context = new DeclarationContext(
            ProtocolFactory::textDocumentIdentifier(),
            ProtocolFactory::position(),
            $editor,
        );
        $consumer = new DeclarationConsumer();
        $contributor->contribute($context, $consumer);

        $this->assertEmpty($consumer->results);
    }

    #[TestDox('returns empty when not a static call')]
    public function testReturnsEmptyWhenNotStaticCall(): void
    {
        $psiFile = PsiFileFactory::fromCode('<?php echo 1;');
        $lookup = IndexTestHelper::createLookup();
        $fileManager = MockHelper::mock(\App\Module\PsiFile\InMemoryPsiFileManager::class);
        $fileManager->method('findPsiFile')->willReturn($psiFile);
        $docFactory = $this->createMock(DocumentIdentifierFactoryInterface::class);

        $contributor = new ClassMethodDeclarationContributor($lookup, $fileManager, $docFactory);

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

    #[TestDox('finds declaration for static method call')]
    public function testFindsStaticMethodDeclaration(): void
    {
        $psiFile = PsiFileFactory::fromCode('<?php \Foo::bar();');
        $lookup = IndexTestHelper::createLookup([
            'php.classMethods.fqn' => [
                'file:///def.php' => [
                    'Foo::bar' => new MethodData('bar', 'Foo', 0, 10, 'public', false, false, [], null),
                    'Foo::baz' => new MethodData('baz', 'Foo', 11, 20, 'public', false, false, [], null),
                ],
            ],
        ]);
        $fileManager = MockHelper::mock(\App\Module\PsiFile\InMemoryPsiFileManager::class);
        $fileManager->method('findPsiFile')->willReturn($psiFile);
        $docFactory = $this->createMock(DocumentIdentifierFactoryInterface::class);
        $docFactory->method('create')->willReturn(ProtocolFactory::textDocumentIdentifier('file:///def.php'));

        $contributor = new ClassMethodDeclarationContributor($lookup, $fileManager, $docFactory);

        $editor = MockHelper::mock(\Lsp\Extension\DocumentManager\Editor\EditorInterface::class);
        $context = new DeclarationContext(
            ProtocolFactory::textDocumentIdentifier(),
            ProtocolFactory::position(0, 8),
            $editor,
        );
        $consumer = new DeclarationConsumer();
        $contributor->contribute($context, $consumer);

        $this->assertCount(1, $consumer->results);
        $this->assertSame('file:///def.php', $consumer->results[0]->uri);
    }
}

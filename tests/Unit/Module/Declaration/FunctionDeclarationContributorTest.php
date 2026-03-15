<?php

declare(strict_types=1);

namespace App\Tests\Unit\Module\Declaration;

use App\Core\Contracts\Declaration\DeclarationConsumer;
use App\Core\Contracts\Declaration\DeclarationContext;
use App\Module\Declaration\FunctionDeclarationContributor;
use App\Module\Document\DocumentIdentifierFactoryInterface;
use App\Module\Indexing\Storage\IndexData\FunctionData;
use App\Tests\Support\IndexTestHelper;
use App\Tests\Support\MockHelper;
use App\Tests\Support\ProtocolFactory;
use App\Tests\Support\PsiFileFactory;
use App\Tests\TestCase;
use Lsp\Protocol\Type\TextDocumentIdentifier;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\TestDox;

#[Group('unit')]
final class FunctionDeclarationContributorTest extends TestCase
{
    #[TestDox('returns empty when document not found')]
    public function testReturnsEmptyWhenNoDocument(): void
    {
        $lookup = IndexTestHelper::createLookup();
        $fileManager = MockHelper::mock(\App\Module\PsiFile\InMemoryPsiFileManager::class);
        $docFactory = $this->createMock(DocumentIdentifierFactoryInterface::class);

        $editor = MockHelper::mock(\Lsp\Extension\DocumentManager\Editor\EditorInterface::class);
        $editor->method('findByUriString')->willReturn(null);

        $contributor = new FunctionDeclarationContributor($lookup, $fileManager, $docFactory);

        $context = new DeclarationContext(
            ProtocolFactory::textDocumentIdentifier(),
            ProtocolFactory::position(),
            $editor,
        );
        $consumer = new DeclarationConsumer();
        $contributor->contribute($context, $consumer);

        $this->assertEmpty($consumer->results);
    }

    #[TestDox('returns empty when file not found')]
    public function testReturnsEmptyWhenNoFile(): void
    {
        $document = PsiFileFactory::document('<?php echo 1;');
        $lookup = IndexTestHelper::createLookup();
        $fileManager = MockHelper::mock(\App\Module\PsiFile\InMemoryPsiFileManager::class);
        $fileManager->method('findPsiFile')->willReturn(null);
        $docFactory = $this->createMock(DocumentIdentifierFactoryInterface::class);

        $editor = MockHelper::mock(\Lsp\Extension\DocumentManager\Editor\EditorInterface::class);
        $editor->method('findByUriString')->willReturn($document);

        $contributor = new FunctionDeclarationContributor($lookup, $fileManager, $docFactory);

        $context = new DeclarationContext(
            ProtocolFactory::textDocumentIdentifier(),
            ProtocolFactory::position(),
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
        $document = PsiFileFactory::document('<?php echo 1;');
        $lookup = IndexTestHelper::createLookup();
        $fileManager = MockHelper::mock(\App\Module\PsiFile\InMemoryPsiFileManager::class);
        $fileManager->method('findPsiFile')->willReturn($psiFile);
        $docFactory = $this->createMock(DocumentIdentifierFactoryInterface::class);

        $editor = MockHelper::mock(\Lsp\Extension\DocumentManager\Editor\EditorInterface::class);
        $editor->method('findByUriString')->willReturn($document);

        $contributor = new FunctionDeclarationContributor($lookup, $fileManager, $docFactory);

        $context = new DeclarationContext(
            ProtocolFactory::textDocumentIdentifier(),
            ProtocolFactory::position(0, 10),
            $editor,
        );
        $consumer = new DeclarationConsumer();
        $contributor->contribute($context, $consumer);

        $this->assertEmpty($consumer->results);
    }

    #[TestDox('returns empty when not a function call')]
    public function testReturnsEmptyWhenNotFuncCall(): void
    {
        $psiFile = PsiFileFactory::fromCode('<?php new \Foo();');
        $document = PsiFileFactory::document('<?php new \Foo();');
        $lookup = IndexTestHelper::createLookup();
        $fileManager = MockHelper::mock(\App\Module\PsiFile\InMemoryPsiFileManager::class);
        $fileManager->method('findPsiFile')->willReturn($psiFile);
        $docFactory = $this->createMock(DocumentIdentifierFactoryInterface::class);

        $editor = MockHelper::mock(\Lsp\Extension\DocumentManager\Editor\EditorInterface::class);
        $editor->method('findByUriString')->willReturn($document);

        $contributor = new FunctionDeclarationContributor($lookup, $fileManager, $docFactory);

        $context = new DeclarationContext(
            ProtocolFactory::textDocumentIdentifier(),
            ProtocolFactory::position(0, 12),
            $editor,
        );
        $consumer = new DeclarationConsumer();
        $contributor->contribute($context, $consumer);

        $this->assertEmpty($consumer->results);
    }

    #[TestDox('finds function declaration')]
    public function testFindsFunctionDeclaration(): void
    {
        $code = '<?php \foo();';
        $defCode = '<?php function foo(): void {}';
        $psiFile = PsiFileFactory::fromCode($code);
        $defFile = PsiFileFactory::fromCode($defCode, 'file:///def.php');
        $document = PsiFileFactory::document($code);

        $funcStartPos = strpos($defCode, 'function');

        $lookup = IndexTestHelper::createLookup([
            'php.functions.fqn' => [
                'file:///def.php' => ['foo' => new FunctionData('foo', $funcStartPos, strlen($defCode) - 1, [], 'void')],
            ],
        ]);

        $fileManager = MockHelper::mock(\App\Module\PsiFile\InMemoryPsiFileManager::class);
        $fileManager->method('findPsiFile')->willReturnCallback(
            function ($editor, $textDoc) use ($psiFile, $defFile) {
                if ($textDoc->uri === 'file:///def.php') {
                    return $defFile;
                }
                return $psiFile;
            },
        );

        $docFactory = $this->createMock(DocumentIdentifierFactoryInterface::class);
        $docFactory->method('create')->willReturn(new TextDocumentIdentifier('file:///def.php'));

        $editor = MockHelper::mock(\Lsp\Extension\DocumentManager\Editor\EditorInterface::class);
        $editor->method('findByUriString')->willReturn($document);

        $contributor = new FunctionDeclarationContributor($lookup, $fileManager, $docFactory);

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

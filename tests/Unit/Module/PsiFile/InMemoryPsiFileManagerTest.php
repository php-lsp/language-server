<?php

declare(strict_types=1);

namespace App\Tests\Unit\Module\PsiFile;

use App\Module\Document\DocumentLoaderInterface;
use App\Module\PsiFile\InMemoryPsiFileManager;
use App\Module\PsiFile\PHPPsiFile;
use App\Module\PsiFile\PHPPsiFileParser;
use App\Tests\Support\ProtocolFactory;
use App\Tests\Support\PsiFileFactory;
use App\Tests\TestCase;
use Lsp\Dispatcher\DispatcherInterface;
use Lsp\Dispatcher\Result\Provider\ResultProviderInterface;
use Lsp\Extension\DocumentManager\Editor\EditorInterface;
use Lsp\Protocol\Type\TextDocumentIdentifier;
use Psr\Log\LoggerInterface;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\TestDox;

#[Group('unit')]
final class InMemoryPsiFileManagerTest extends TestCase
{
    private function createManager(): InMemoryPsiFileManager
    {
        $dispatcher = $this->createMock(DispatcherInterface::class);
        $resultProvider = $this->createMock(ResultProviderInterface::class);
        $resultProvider->method('getResult')->willReturn([]);
        $logger = $this->createMock(LoggerInterface::class);
        $documentLoader = $this->createMock(DocumentLoaderInterface::class);

        return new InMemoryPsiFileManager(
            PsiFileFactory::getParser(),
            $dispatcher,
            $resultProvider,
            $logger,
            $documentLoader,
        );
    }

    #[TestDox('refreshFile parses document and returns PHPPsiFile')]
    public function testRefreshFile(): void
    {
        $manager = $this->createManager();
        $document = PsiFileFactory::document('<?php class Foo {}');
        $identifier = ProtocolFactory::textDocumentIdentifier();

        $result = $manager->refreshFile($document, $identifier);

        $this->assertInstanceOf(PHPPsiFile::class, $result);
        $this->assertNotEmpty($result->ast->children);
    }

    #[TestDox('refreshFile throws when document is null')]
    public function testRefreshFileThrowsOnNull(): void
    {
        $manager = $this->createManager();

        $this->expectException(\RuntimeException::class);
        $manager->refreshFile(null, ProtocolFactory::textDocumentIdentifier());
    }

    #[TestDox('refreshFile publishes diagnostics for code with errors')]
    public function testRefreshFileWithErrors(): void
    {
        $dispatcher = $this->createMock(DispatcherInterface::class);
        $dispatcher->expects($this->once())->method('notify');

        $resultProvider = $this->createMock(ResultProviderInterface::class);
        $resultProvider->method('getResult')->willReturn([]);
        $logger = $this->createMock(LoggerInterface::class);
        $documentLoader = $this->createMock(DocumentLoaderInterface::class);

        $manager = new InMemoryPsiFileManager(
            PsiFileFactory::getParser(),
            $dispatcher,
            $resultProvider,
            $logger,
            $documentLoader,
        );

        $document = PsiFileFactory::document('<?php echo $x; echo;');
        $result = $manager->refreshFile($document, ProtocolFactory::textDocumentIdentifier());

        $this->assertInstanceOf(PHPPsiFile::class, $result);
    }

    #[TestDox('findPsiFile returns parsed file')]
    public function testFindPsiFile(): void
    {
        $manager = $this->createManager();
        $document = PsiFileFactory::document('<?php class Foo {}');

        $editor = $this->createMock(EditorInterface::class);
        $editor->method('findByUriString')->willReturn($document);

        $identifier = ProtocolFactory::textDocumentIdentifier();
        $result = $manager->findPsiFile($editor, $identifier);

        $this->assertInstanceOf(PHPPsiFile::class, $result);
    }

    #[TestDox('findPsiFile caches result')]
    public function testFindPsiFileCaches(): void
    {
        $manager = $this->createManager();
        $document = PsiFileFactory::document('<?php class Foo {}');

        $editor = $this->createMock(EditorInterface::class);
        $editor->method('findByUriString')->willReturn($document);

        $identifier = ProtocolFactory::textDocumentIdentifier();
        $first = $manager->findPsiFile($editor, $identifier);
        $second = $manager->findPsiFile($editor, $identifier);

        $this->assertSame($first, $second);
    }

    #[TestDox('findPsiFileByUri loads and parses')]
    public function testFindPsiFileByUri(): void
    {
        $document = PsiFileFactory::document('<?php class Bar {}');
        $documentLoader = $this->createMock(DocumentLoaderInterface::class);
        $documentLoader->method('load')->willReturn($document);

        $dispatcher = $this->createMock(DispatcherInterface::class);
        $resultProvider = $this->createMock(ResultProviderInterface::class);
        $resultProvider->method('getResult')->willReturn([]);
        $logger = $this->createMock(LoggerInterface::class);

        $manager = new InMemoryPsiFileManager(
            PsiFileFactory::getParser(),
            $dispatcher,
            $resultProvider,
            $logger,
            $documentLoader,
        );

        $uri = \Lsp\Workspace\Uri\Uri::createLocal('/tmp/test.php');
        $result = $manager->findPsiFileByUri($uri);

        $this->assertInstanceOf(PHPPsiFile::class, $result);
    }
}

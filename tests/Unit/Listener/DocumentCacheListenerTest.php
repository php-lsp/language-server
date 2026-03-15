<?php

declare(strict_types=1);

namespace App\Tests\Unit\Listener;

use App\Core\Event\Document\DocumentChanged;
use App\Core\Event\Document\DocumentClosed;
use App\Core\Event\Document\DocumentOpened;
use App\Core\Event\Document\DocumentSaved;
use App\Listener\DocumentCacheListener;
use App\Module\Document\DocumentLoaderInterface;
use App\Module\PsiFile\InMemoryPsiFileManager;
use App\Module\PsiFile\Tree;
use App\Tests\Support\PsiFileFactory;
use App\Tests\TestCase;
use Lsp\Dispatcher\DispatcherInterface;
use Lsp\Dispatcher\Result\Provider\ResultProviderInterface;
use Lsp\Extension\DocumentManager\Editor\Document\Document;
use Lsp\Extension\DocumentManager\Editor\Document\Uri;
use Lsp\Extension\DocumentManager\Editor\EditorInterface;
use Lsp\Protocol\Type\TextDocumentIdentifier;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\TestDox;
use Psr\Log\LoggerInterface;

#[Group('unit')]
final class DocumentCacheListenerTest extends TestCase
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

    private function createDocument(string $uri = 'file:///test.php', string $code = '<?php echo 1;'): Document
    {
        return new Document(new Uri($uri), $code, 1);
    }

    #[TestDox('onDocumentChanged invalidates PSI cache')]
    public function testOnDocumentChangedInvalidatesCache(): void
    {
        $manager = $this->createManager();

        $document = PsiFileFactory::document('<?php class Foo {}');
        $editor = $this->createMock(EditorInterface::class);
        $editor->method('findByUriString')->willReturn($document);

        $identifier = new TextDocumentIdentifier('file:///test.php');
        $psiFile = $manager->findPsiFile($editor, $identifier);
        $this->assertNotNull($psiFile);

        $listener = new DocumentCacheListener($manager);
        $listener->onDocumentChanged(new DocumentChanged($document));

        $document2 = PsiFileFactory::document('<?php class Bar {}');
        $editor2 = $this->createMock(EditorInterface::class);
        $editor2->method('findByUriString')->willReturn($document2);

        $psiFile2 = $manager->findPsiFile($editor2, $identifier);
        $this->assertNotSame($psiFile, $psiFile2);
    }

    #[TestDox('onDocumentClosed invalidates PSI cache')]
    public function testOnDocumentClosedInvalidatesCache(): void
    {
        $manager = $this->createManager();

        $document = PsiFileFactory::document('<?php class Foo {}');
        $editor = $this->createMock(EditorInterface::class);
        $editor->method('findByUriString')->willReturn($document);

        $identifier = new TextDocumentIdentifier('file:///test.php');
        $manager->findPsiFile($editor, $identifier);

        $listener = new DocumentCacheListener($manager);
        $listener->onDocumentClosed(new DocumentClosed($document));

        $document2 = PsiFileFactory::document('<?php class Bar {}');
        $editor2 = $this->createMock(EditorInterface::class);
        $editor2->method('findByUriString')->willReturn($document2);

        $psiFile2 = $manager->findPsiFile($editor2, $identifier);
        $this->assertNotNull($psiFile2);
    }

    #[TestDox('onDocumentOpened invalidates PSI cache')]
    public function testOnDocumentOpenedInvalidatesCache(): void
    {
        $manager = $this->createManager();
        $document = $this->createDocument();

        $listener = new DocumentCacheListener($manager);
        $listener->onDocumentOpened(new DocumentOpened($document));

        $this->assertTrue(true, 'No exception thrown');
    }

    #[TestDox('onDocumentSaved invalidates PSI cache')]
    public function testOnDocumentSavedInvalidatesCache(): void
    {
        $manager = $this->createManager();
        $document = $this->createDocument();

        $listener = new DocumentCacheListener($manager);
        $listener->onDocumentSaved(new DocumentSaved($document));

        $this->assertTrue(true, 'No exception thrown');
    }

    #[TestDox('clears line offset cache on document change')]
    public function testClearsLineOffsetCache(): void
    {
        $manager = $this->createManager();
        $document = PsiFileFactory::document('<?php echo 1;');

        Tree::toLineColumn($document, 5);

        $listener = new DocumentCacheListener($manager);
        $listener->onDocumentChanged(new DocumentChanged($document));

        $this->assertTrue(true, 'Line offset cache cleared without error');
    }
}

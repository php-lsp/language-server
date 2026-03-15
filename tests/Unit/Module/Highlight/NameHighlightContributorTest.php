<?php

declare(strict_types=1);

namespace App\Tests\Unit\Module\Highlight;

use App\Core\Contracts\Highlight\DocumentHighlightConsumer;
use App\Core\Contracts\Highlight\DocumentHighlightContext;
use App\Module\Highlight\NameHighlightContributor;
use App\Tests\Support\MockHelper;
use App\Tests\Support\ProtocolFactory;
use App\Tests\Support\PsiFileFactory;
use App\Tests\TestCase;
use Lsp\Protocol\Type\DocumentHighlight;
use Lsp\Protocol\Type\DocumentHighlightKind;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\TestDox;

#[Group('unit')]
final class NameHighlightContributorTest extends TestCase
{
    #[TestDox('returns empty when file not found')]
    public function testReturnsEmptyWhenNoFile(): void
    {
        $fileManager = MockHelper::mock(\App\Module\PsiFile\InMemoryPsiFileManager::class);
        $fileManager->method('findPsiFile')->willReturn(null);

        $contributor = new NameHighlightContributor();

        $editor = MockHelper::mock(\Lsp\Extension\DocumentManager\Editor\EditorInterface::class);
        $context = new DocumentHighlightContext(
            ProtocolFactory::textDocumentIdentifier(),
            ProtocolFactory::position(0, 10),
            $editor,
            $fileManager,
        );
        $consumer = new DocumentHighlightConsumer();
        $contributor->contribute($context, $consumer);

        $this->assertEmpty($consumer->results);
    }

    #[TestDox('returns empty when node is not FullyQualified')]
    public function testReturnsEmptyWhenNotFullyQualified(): void
    {
        $psiFile = PsiFileFactory::fromCode('<?php echo 1;');
        $fileManager = MockHelper::mock(\App\Module\PsiFile\InMemoryPsiFileManager::class);
        $fileManager->method('findPsiFile')->willReturn($psiFile);

        $contributor = new NameHighlightContributor();

        $editor = MockHelper::mock(\Lsp\Extension\DocumentManager\Editor\EditorInterface::class);
        $context = new DocumentHighlightContext(
            ProtocolFactory::textDocumentIdentifier(),
            ProtocolFactory::position(0, 6),
            $editor,
            $fileManager,
        );
        $consumer = new DocumentHighlightConsumer();
        $contributor->contribute($context, $consumer);

        $this->assertEmpty($consumer->results);
    }

    #[TestDox('highlights all occurrences of a fully qualified name')]
    public function testHighlightsAllOccurrences(): void
    {
        $code = '<?php new \Foo(); new \Foo(); new \Bar();';
        $psiFile = PsiFileFactory::fromCode($code);
        $fileManager = MockHelper::mock(\App\Module\PsiFile\InMemoryPsiFileManager::class);
        $fileManager->method('findPsiFile')->willReturn($psiFile);

        $contributor = new NameHighlightContributor();

        $editor = MockHelper::mock(\Lsp\Extension\DocumentManager\Editor\EditorInterface::class);
        $context = new DocumentHighlightContext(
            ProtocolFactory::textDocumentIdentifier(),
            ProtocolFactory::position(0, 12),
            $editor,
            $fileManager,
        );
        $consumer = new DocumentHighlightConsumer();
        $contributor->contribute($context, $consumer);

        // Should find 2 occurrences of \Foo, not \Bar
        $this->assertCount(2, $consumer->results);
        foreach ($consumer->results as $highlight) {
            $this->assertInstanceOf(DocumentHighlight::class, $highlight);
            $this->assertSame(DocumentHighlightKind::Text, $highlight->kind);
        }
    }
}

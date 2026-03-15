<?php

declare(strict_types=1);

namespace App\Tests\Unit\Module\Highlight;

use App\Core\Contracts\Highlight\DocumentHighlightConsumer;
use App\Core\Contracts\Highlight\DocumentHighlightContext;
use App\Module\Highlight\VariableHighlightContributor;
use App\Tests\Support\MockHelper;
use App\Tests\Support\ProtocolFactory;
use App\Tests\Support\PsiFileFactory;
use App\Tests\TestCase;
use Lsp\Protocol\Type\DocumentHighlight;
use Lsp\Protocol\Type\DocumentHighlightKind;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\TestDox;

#[Group('unit')]
final class VariableHighlightContributorTest extends TestCase
{
    #[TestDox('returns empty when file not found')]
    public function testReturnsEmptyWhenNoFile(): void
    {
        $fileManager = MockHelper::mock(\App\Module\PsiFile\InMemoryPsiFileManager::class);
        $fileManager->method('findPsiFile')->willReturn(null);

        $contributor = new VariableHighlightContributor();

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

    #[TestDox('returns empty when node is not a variable')]
    public function testReturnsEmptyWhenNotVariable(): void
    {
        $psiFile = PsiFileFactory::fromCode('<?php echo 1;');
        $fileManager = MockHelper::mock(\App\Module\PsiFile\InMemoryPsiFileManager::class);
        $fileManager->method('findPsiFile')->willReturn($psiFile);

        $contributor = new VariableHighlightContributor();

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

    #[TestDox('highlights variable occurrences in function scope')]
    public function testHighlightsVariablesInScope(): void
    {
        $code = '<?php function foo($x) { $x = 1; echo $x; }';
        $psiFile = PsiFileFactory::fromCode($code);
        $fileManager = MockHelper::mock(\App\Module\PsiFile\InMemoryPsiFileManager::class);
        $fileManager->method('findPsiFile')->willReturn($psiFile);

        $contributor = new VariableHighlightContributor();

        $editor = MockHelper::mock(\Lsp\Extension\DocumentManager\Editor\EditorInterface::class);
        $context = new DocumentHighlightContext(
            ProtocolFactory::textDocumentIdentifier(),
            ProtocolFactory::position(0, 40),
            $editor,
            $fileManager,
        );
        $consumer = new DocumentHighlightConsumer();
        $contributor->contribute($context, $consumer);

        // Should find parameter + 2 usages in body ($x = 1, echo $x)
        $this->assertNotEmpty($consumer->results);
        foreach ($consumer->results as $highlight) {
            $this->assertInstanceOf(DocumentHighlight::class, $highlight);
            $this->assertContains($highlight->kind, [DocumentHighlightKind::Read, DocumentHighlightKind::Write]);
        }
    }
}

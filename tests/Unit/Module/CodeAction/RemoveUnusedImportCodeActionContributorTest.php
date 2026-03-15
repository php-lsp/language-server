<?php

declare(strict_types=1);

namespace App\Tests\Unit\Module\CodeAction;

use App\Core\Contracts\CodeAction\CodeActionConsumer;
use App\Core\Contracts\CodeAction\CodeActionContext;
use App\Module\CodeAction\RemoveUnusedImportCodeActionContributor;
use App\Tests\Support\MockHelper;
use App\Tests\Support\ProtocolFactory;
use App\Tests\Support\PsiFileFactory;
use App\Tests\TestCase;
use Lsp\Protocol\Type\CodeAction;
use Lsp\Protocol\Type\CodeActionContext as LspCodeActionContext;
use Lsp\Protocol\Type\CodeActionKind;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\TestDox;

#[Group('unit')]
final class RemoveUnusedImportCodeActionContributorTest extends TestCase
{
    #[TestDox('returns empty when file not found')]
    public function testReturnsEmptyWhenNoFile(): void
    {
        $fileManager = MockHelper::mock(\App\Module\PsiFile\InMemoryPsiFileManager::class);
        $fileManager->method('findPsiFile')->willReturn(null);

        $contributor = new RemoveUnusedImportCodeActionContributor($fileManager);

        $editor = MockHelper::mock(\Lsp\Extension\DocumentManager\Editor\EditorInterface::class);
        $context = new CodeActionContext(
            ProtocolFactory::textDocumentIdentifier(),
            ProtocolFactory::range(),
            new LspCodeActionContext(),
            $editor,
        );
        $consumer = new CodeActionConsumer();
        $contributor->contribute($context, $consumer);

        $this->assertEmpty($consumer->results);
    }

    #[TestDox('returns empty when no use statements')]
    public function testReturnsEmptyWhenNoUseStatements(): void
    {
        $psiFile = PsiFileFactory::fromCode('<?php echo 1;');
        $fileManager = MockHelper::mock(\App\Module\PsiFile\InMemoryPsiFileManager::class);
        $fileManager->method('findPsiFile')->willReturn($psiFile);

        $contributor = new RemoveUnusedImportCodeActionContributor($fileManager);

        $editor = MockHelper::mock(\Lsp\Extension\DocumentManager\Editor\EditorInterface::class);
        $context = new CodeActionContext(
            ProtocolFactory::textDocumentIdentifier(),
            ProtocolFactory::range(),
            new LspCodeActionContext(),
            $editor,
        );
        $consumer = new CodeActionConsumer();
        $contributor->contribute($context, $consumer);

        $this->assertEmpty($consumer->results);
    }

    #[TestDox('suggests removing unused import')]
    public function testSuggestsRemovingUnusedImport(): void
    {
        $code = <<<'PHP'
<?php

namespace App;

use App\Models\Foo;
use App\Models\Bar;

$bar = new Bar();
PHP;

        $psiFile = PsiFileFactory::fromCode($code);
        $fileManager = MockHelper::mock(\App\Module\PsiFile\InMemoryPsiFileManager::class);
        $fileManager->method('findPsiFile')->willReturn($psiFile);

        $contributor = new RemoveUnusedImportCodeActionContributor($fileManager);

        $editor = MockHelper::mock(\Lsp\Extension\DocumentManager\Editor\EditorInterface::class);
        $context = new CodeActionContext(
            ProtocolFactory::textDocumentIdentifier(),
            ProtocolFactory::range(),
            new LspCodeActionContext(),
            $editor,
        );
        $consumer = new CodeActionConsumer();
        $contributor->contribute($context, $consumer);

        $hasRemoveFoo = false;
        foreach ($consumer->results as $result) {
            $this->assertInstanceOf(CodeAction::class, $result);
            $this->assertSame(CodeActionKind::QuickFix, $result->kind);
            if (str_contains($result->title, 'Foo')) {
                $hasRemoveFoo = true;
            }
        }

        $this->assertTrue($hasRemoveFoo, 'Should suggest removing unused Foo import');
    }

    #[TestDox('does not suggest removing used import')]
    public function testDoesNotSuggestRemovingUsedImport(): void
    {
        $code = <<<'PHP'
<?php

namespace App;

use App\Models\Bar;

$bar = new Bar();
PHP;

        $psiFile = PsiFileFactory::fromCode($code);
        $fileManager = MockHelper::mock(\App\Module\PsiFile\InMemoryPsiFileManager::class);
        $fileManager->method('findPsiFile')->willReturn($psiFile);

        $contributor = new RemoveUnusedImportCodeActionContributor($fileManager);

        $editor = MockHelper::mock(\Lsp\Extension\DocumentManager\Editor\EditorInterface::class);
        $context = new CodeActionContext(
            ProtocolFactory::textDocumentIdentifier(),
            ProtocolFactory::range(),
            new LspCodeActionContext(),
            $editor,
        );
        $consumer = new CodeActionConsumer();
        $contributor->contribute($context, $consumer);

        $barResults = array_filter(
            $consumer->results,
            static fn(CodeAction $result) => str_contains($result->title, 'Bar'),
        );
        $this->assertEmpty($barResults, 'Should not suggest removing used Bar import');
    }
}

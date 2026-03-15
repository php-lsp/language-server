<?php

declare(strict_types=1);

namespace App\Tests\Unit\Module\CodeAction;

use App\Core\Contracts\CodeAction\CodeActionConsumer;
use App\Core\Contracts\CodeAction\CodeActionContext;
use App\Module\CodeAction\ImportSymbolCodeActionContributor;
use App\Module\Indexing\Data\ClassData;
use App\Module\Indexing\Data\InterfaceData;
use App\Tests\Support\IndexTestHelper;
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
final class ImportSymbolCodeActionContributorTest extends TestCase
{
    #[TestDox('returns empty when file not found')]
    public function testReturnsEmptyWhenNoFile(): void
    {
        $lookup = IndexTestHelper::createLookup();
        $fileManager = MockHelper::mock(\App\Module\PsiFile\InMemoryPsiFileManager::class);
        $fileManager->method('findPsiFile')->willReturn(null);

        $contributor = new ImportSymbolCodeActionContributor($lookup, $fileManager);

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

    #[TestDox('returns empty when no element at position')]
    public function testReturnsEmptyWhenNoElement(): void
    {
        $psiFile = PsiFileFactory::fromCode('<?php ');
        $lookup = IndexTestHelper::createLookup();
        $fileManager = MockHelper::mock(\App\Module\PsiFile\InMemoryPsiFileManager::class);
        $fileManager->method('findPsiFile')->willReturn($psiFile);

        $contributor = new ImportSymbolCodeActionContributor($lookup, $fileManager);

        $editor = MockHelper::mock(\Lsp\Extension\DocumentManager\Editor\EditorInterface::class);
        $context = new CodeActionContext(
            ProtocolFactory::textDocumentIdentifier(),
            ProtocolFactory::range(ProtocolFactory::position(0, 6), ProtocolFactory::position(0, 6)),
            new LspCodeActionContext(),
            $editor,
        );
        $consumer = new CodeActionConsumer();
        $contributor->contribute($context, $consumer);

        $this->assertEmpty($consumer->results);
    }

    #[TestDox('suggests import for matching class name')]
    public function testSuggestsImportForMatchingClass(): void
    {
        $code = <<<'PHP'
<?php

namespace App;

new Foo();
PHP;
        $psiFile = PsiFileFactory::fromCode($code);
        $lookup = IndexTestHelper::createLookup([
            'php.classes.fqn' => [
                'file:///vendor.php' => [
                    'App\\Models\\Foo' => new ClassData('App\\Models\\Foo', 0, 50, false, false, false, null, []),
                ],
            ],
        ]);

        $fileManager = MockHelper::mock(\App\Module\PsiFile\InMemoryPsiFileManager::class);
        $fileManager->method('findPsiFile')->willReturn($psiFile);

        $contributor = new ImportSymbolCodeActionContributor($lookup, $fileManager);

        $editor = MockHelper::mock(\Lsp\Extension\DocumentManager\Editor\EditorInterface::class);
        $context = new CodeActionContext(
            ProtocolFactory::textDocumentIdentifier(),
            ProtocolFactory::range(ProtocolFactory::position(4, 5), ProtocolFactory::position(4, 8)),
            new LspCodeActionContext(),
            $editor,
        );
        $consumer = new CodeActionConsumer();
        $contributor->contribute($context, $consumer);

        $this->assertCount(1, $consumer->results);
        $this->assertInstanceOf(CodeAction::class, $consumer->results[0]);
        $this->assertSame('Import App\\Models\\Foo', $consumer->results[0]->title);
        $this->assertSame(CodeActionKind::QuickFix, $consumer->results[0]->kind);
    }

    #[TestDox('does not suggest import for already qualified name')]
    public function testDoesNotSuggestForFullyQualified(): void
    {
        $psiFile = PsiFileFactory::fromCode('<?php new \\App\\Foo();');
        $lookup = IndexTestHelper::createLookup([
            'php.classes.fqn' => [
                'file:///def.php' => [
                    'App\\Foo' => new ClassData('App\\Foo', 0, 50, false, false, false, null, []),
                ],
            ],
        ]);

        $fileManager = MockHelper::mock(\App\Module\PsiFile\InMemoryPsiFileManager::class);
        $fileManager->method('findPsiFile')->willReturn($psiFile);

        $contributor = new ImportSymbolCodeActionContributor($lookup, $fileManager);

        $editor = MockHelper::mock(\Lsp\Extension\DocumentManager\Editor\EditorInterface::class);
        $context = new CodeActionContext(
            ProtocolFactory::textDocumentIdentifier(),
            ProtocolFactory::range(ProtocolFactory::position(0, 11), ProtocolFactory::position(0, 18)),
            new LspCodeActionContext(),
            $editor,
        );
        $consumer = new CodeActionConsumer();
        $contributor->contribute($context, $consumer);

        $this->assertEmpty($consumer->results);
    }
}

<?php

declare(strict_types=1);

namespace App\Tests\Unit\Module\FoldingRange;

use App\Core\Contracts\FoldingRange\FoldingRangeConsumer;
use App\Core\Contracts\FoldingRange\FoldingRangeContext;
use App\Module\FoldingRange\UseBlockFoldingRangeContributor;
use App\Module\PsiFile\InMemoryPsiFileManager;
use App\Tests\Support\MockHelper;
use App\Tests\Support\ProtocolFactory;
use App\Tests\Support\PsiFileFactory;
use App\Tests\TestCase;
use Lsp\Extension\DocumentManager\Editor\EditorInterface;
use Lsp\Protocol\Type\FoldingRange;
use Lsp\Protocol\Type\FoldingRangeKind;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\TestDox;

#[Group('unit')]
final class UseBlockFoldingRangeContributorTest extends TestCase
{
    #[TestDox('returns empty when file not found')]
    public function testReturnsEmptyWhenNoFile(): void
    {
        $fileManager = MockHelper::mock(InMemoryPsiFileManager::class);
        $fileManager->method('findPsiFile')->willReturn(null);

        $contributor = new UseBlockFoldingRangeContributor();
        $editor = MockHelper::mock(EditorInterface::class);
        $context = new FoldingRangeContext(
            ProtocolFactory::textDocumentIdentifier(),
            $editor,
            $fileManager,
        );
        $consumer = new FoldingRangeConsumer();
        $contributor->contribute($context, $consumer);

        $this->assertEmpty($consumer->results);
    }

    #[TestDox('produces folding range for use block')]
    public function testUseBlockFolding(): void
    {
        $code = <<<'PHP'
<?php
use Foo\Bar;
use Foo\Baz;
use Foo\Qux;

class MyClass {}
PHP;
        $results = $this->getFoldingRanges($code);

        $this->assertNotEmpty($results, 'Use block should produce an imports folding range');
        $useRange = $results[0];
        $this->assertSame(1, $useRange->startLine);
        $this->assertSame(3, $useRange->endLine);
        $this->assertSame(FoldingRangeKind::Imports, $useRange->kind);
    }

    #[TestDox('does not produce use block range for single use statement')]
    public function testSingleUseNoBlock(): void
    {
        $code = <<<'PHP'
<?php
use Foo\Bar;

class MyClass {}
PHP;
        $results = $this->getFoldingRanges($code);

        $this->assertEmpty($results, 'Single use statement should not produce imports range');
    }

    #[TestDox('produces folding range for namespaced use block')]
    public function testNamespacedUseBlockFolding(): void
    {
        $code = <<<'PHP'
<?php
namespace App;

use Foo\Bar;
use Foo\Baz;
use Foo\Qux;

class MyClass {}
PHP;
        $results = $this->getFoldingRanges($code);

        $this->assertNotEmpty($results, 'Namespaced use block should produce an imports folding range');
        $this->assertSame(FoldingRangeKind::Imports, $results[0]->kind);
    }

    /**
     * @return list<FoldingRange>
     */
    private function getFoldingRanges(string $code): array
    {
        $psiFile = PsiFileFactory::fromCode($code);
        $fileManager = MockHelper::mock(InMemoryPsiFileManager::class);
        $fileManager->method('findPsiFile')->willReturn($psiFile);

        $contributor = new UseBlockFoldingRangeContributor();
        $editor = MockHelper::mock(EditorInterface::class);
        $context = new FoldingRangeContext(
            ProtocolFactory::textDocumentIdentifier(),
            $editor,
            $fileManager,
        );
        $consumer = new FoldingRangeConsumer();
        $contributor->contribute($context, $consumer);

        return $consumer->results;
    }
}

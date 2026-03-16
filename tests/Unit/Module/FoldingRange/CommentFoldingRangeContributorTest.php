<?php

declare(strict_types=1);

namespace App\Tests\Unit\Module\FoldingRange;

use App\Core\Contracts\FoldingRange\FoldingRangeConsumer;
use App\Core\Contracts\FoldingRange\FoldingRangeContext;
use App\Module\FoldingRange\CommentFoldingRangeContributor;
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
final class CommentFoldingRangeContributorTest extends TestCase
{
    #[TestDox('returns empty when file not found')]
    public function testReturnsEmptyWhenNoFile(): void
    {
        $fileManager = MockHelper::mock(InMemoryPsiFileManager::class);
        $fileManager->method('findPsiFile')->willReturn(null);

        $contributor = new CommentFoldingRangeContributor();
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

    #[TestDox('produces folding range for multi-line docblock')]
    public function testDocblockFolding(): void
    {
        $code = <<<'PHP'
<?php
/**
 * This is a docblock
 * with multiple lines.
 */
function hello(): void {}
PHP;
        $results = $this->getFoldingRanges($code);

        $this->assertNotEmpty($results, 'Docblock should produce a comment folding range');
        $docRange = $results[0];
        $this->assertSame(1, $docRange->startLine);
        $this->assertSame(4, $docRange->endLine);
        $this->assertSame(FoldingRangeKind::Comment, $docRange->kind);
    }

    #[TestDox('produces folding range for multi-line block comment')]
    public function testBlockCommentFolding(): void
    {
        $code = <<<'PHP'
<?php
/*
 * This is a block comment
 * spanning multiple lines.
 */
echo 'hello';
PHP;
        $results = $this->getFoldingRanges($code);

        $this->assertNotEmpty($results, 'Block comment should produce a folding range');
        $this->assertSame(1, $results[0]->startLine);
        $this->assertSame(4, $results[0]->endLine);
    }

    #[TestDox('skips single-line comments')]
    public function testSkipsSingleLineComments(): void
    {
        $code = <<<'PHP'
<?php
// This is a single line comment
echo 'hello';
PHP;
        $results = $this->getFoldingRanges($code);

        $this->assertEmpty($results, 'Single-line comment should not produce a folding range');
    }

    #[TestDox('does not duplicate comment ranges')]
    public function testNoDuplicateComments(): void
    {
        $code = <<<'PHP'
<?php
/**
 * Docblock.
 */
function foo(): void {}
PHP;
        $results = $this->getFoldingRanges($code);

        // Should only have one range for the docblock (not duplicated)
        $matchingRanges = array_filter(
            $results,
            static fn(FoldingRange $r): bool => $r->startLine === 1 && $r->endLine === 3,
        );
        $this->assertCount(1, $matchingRanges, 'Should not have duplicate comment ranges');
    }

    /**
     * @return list<FoldingRange>
     */
    private function getFoldingRanges(string $code): array
    {
        $psiFile = PsiFileFactory::fromCode($code);
        $fileManager = MockHelper::mock(InMemoryPsiFileManager::class);
        $fileManager->method('findPsiFile')->willReturn($psiFile);

        $contributor = new CommentFoldingRangeContributor();
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

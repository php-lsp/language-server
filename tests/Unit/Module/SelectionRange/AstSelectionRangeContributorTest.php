<?php

declare(strict_types=1);

namespace App\Tests\Unit\Module\SelectionRange;

use App\Core\Contracts\SelectionRange\SelectionRangeConsumer;
use App\Core\Contracts\SelectionRange\SelectionRangeContext;
use App\Module\PsiFile\InMemoryPsiFileManager;
use App\Module\SelectionRange\AstSelectionRangeContributor;
use App\Tests\Support\MockHelper;
use App\Tests\Support\ProtocolFactory;
use App\Tests\Support\PsiFileFactory;
use App\Tests\TestCase;
use Lsp\Protocol\Type\SelectionRange;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\TestDox;

#[Group('unit')]
final class AstSelectionRangeContributorTest extends TestCase
{
    #[TestDox('returns empty when file not found')]
    public function testReturnsEmptyWhenNoFile(): void
    {
        $fileManager = MockHelper::mock(InMemoryPsiFileManager::class);
        $fileManager->method('findPsiFile')->willReturn(null);

        $contributor = new AstSelectionRangeContributor($fileManager);

        $editor = MockHelper::mock(\Lsp\Extension\DocumentManager\Editor\EditorInterface::class);
        $context = new SelectionRangeContext(
            ProtocolFactory::textDocumentIdentifier(),
            [ProtocolFactory::position(0, 5)],
            $editor,
        );
        $consumer = new SelectionRangeConsumer();
        $contributor->contribute($context, $consumer);

        $this->assertEmpty($consumer->results);
    }

    #[TestDox('returns empty when position has no nodes')]
    public function testReturnsEmptyWhenNoNodes(): void
    {
        $psiFile = PsiFileFactory::fromCode('<?php ');
        $fileManager = MockHelper::mock(InMemoryPsiFileManager::class);
        $fileManager->method('findPsiFile')->willReturn($psiFile);

        $contributor = new AstSelectionRangeContributor($fileManager);

        $editor = MockHelper::mock(\Lsp\Extension\DocumentManager\Editor\EditorInterface::class);
        $context = new SelectionRangeContext(
            ProtocolFactory::textDocumentIdentifier(),
            [ProtocolFactory::position(0, 5)],
            $editor,
        );
        $consumer = new SelectionRangeConsumer();
        $contributor->contribute($context, $consumer);

        $this->assertEmpty($consumer->results);
    }

    #[TestDox('builds nested ranges for variable in method body')]
    public function testNestedRangesForMethodBody(): void
    {
        $code = <<<'PHP'
        <?php

        namespace App;

        class Foo
        {
            public function bar(): void
            {
                $x = 1;
            }
        }
        PHP;

        $psiFile = PsiFileFactory::fromCode($code);
        $fileManager = MockHelper::mock(InMemoryPsiFileManager::class);
        $fileManager->method('findPsiFile')->willReturn($psiFile);

        $contributor = new AstSelectionRangeContributor($fileManager);

        $editor = MockHelper::mock(\Lsp\Extension\DocumentManager\Editor\EditorInterface::class);
        // Position on $x = 1 (line 8, character 9 = on '$x')
        $context = new SelectionRangeContext(
            ProtocolFactory::textDocumentIdentifier(),
            [ProtocolFactory::position(8, 9)],
            $editor,
        );
        $consumer = new SelectionRangeConsumer();
        $contributor->contribute($context, $consumer);

        $this->assertArrayHasKey(0, $consumer->results);
        $result = $consumer->results[0];
        $this->assertInstanceOf(SelectionRange::class, $result);

        // The innermost range should be for the variable node or expression.
        // Walk up the parent chain to verify nesting.
        $ranges = $this->collectRangeChain($result);
        $this->assertGreaterThanOrEqual(3, count($ranges), 'Expected at least 3 nested ranges (variable -> expression -> statement -> ...)');

        // Verify each parent range contains the child range
        for ($i = 1, $count = count($ranges); $i < $count; $i++) {
            $child = $ranges[$i - 1];
            $parent = $ranges[$i];

            $this->assertRangeContains($parent, $child, "Parent range #{$i} must contain child range #" . ($i - 1));
        }
    }

    #[TestDox('handles multiple positions')]
    public function testMultiplePositions(): void
    {
        $code = <<<'PHP'
        <?php

        $a = 1;
        $b = 2;
        PHP;

        $psiFile = PsiFileFactory::fromCode($code);
        $fileManager = MockHelper::mock(InMemoryPsiFileManager::class);
        $fileManager->method('findPsiFile')->willReturn($psiFile);

        $contributor = new AstSelectionRangeContributor($fileManager);

        $editor = MockHelper::mock(\Lsp\Extension\DocumentManager\Editor\EditorInterface::class);
        $context = new SelectionRangeContext(
            ProtocolFactory::textDocumentIdentifier(),
            [
                ProtocolFactory::position(2, 1), // on $a
                ProtocolFactory::position(3, 1), // on $b
            ],
            $editor,
        );
        $consumer = new SelectionRangeConsumer();
        $contributor->contribute($context, $consumer);

        $this->assertArrayHasKey(0, $consumer->results);
        $this->assertArrayHasKey(1, $consumer->results);
        $this->assertInstanceOf(SelectionRange::class, $consumer->results[0]);
        $this->assertInstanceOf(SelectionRange::class, $consumer->results[1]);
    }

    #[TestDox('builds range chain for simple expression')]
    public function testSimpleExpression(): void
    {
        $code = '<?php echo "hello";';

        $psiFile = PsiFileFactory::fromCode($code);
        $fileManager = MockHelper::mock(InMemoryPsiFileManager::class);
        $fileManager->method('findPsiFile')->willReturn($psiFile);

        $contributor = new AstSelectionRangeContributor($fileManager);

        $editor = MockHelper::mock(\Lsp\Extension\DocumentManager\Editor\EditorInterface::class);
        // Position on "hello" string
        $context = new SelectionRangeContext(
            ProtocolFactory::textDocumentIdentifier(),
            [ProtocolFactory::position(0, 12)],
            $editor,
        );
        $consumer = new SelectionRangeConsumer();
        $contributor->contribute($context, $consumer);

        $this->assertArrayHasKey(0, $consumer->results);
        $result = $consumer->results[0];
        $this->assertInstanceOf(SelectionRange::class, $result);

        // The innermost should have a parent
        $this->assertNotNull($result->parent, 'String literal should have a parent range (echo statement)');
    }

    #[TestDox('produces nested ranges from innermost to class level for class member')]
    public function testClassMemberNesting(): void
    {
        $code = <<<'PHP'
        <?php

        class Calculator
        {
            private float $result = 0.0;

            public function add(float $value): self
            {
                $this->result += $value;
                return $this;
            }
        }
        PHP;

        $psiFile = PsiFileFactory::fromCode($code);
        $fileManager = MockHelper::mock(InMemoryPsiFileManager::class);
        $fileManager->method('findPsiFile')->willReturn($psiFile);

        $contributor = new AstSelectionRangeContributor($fileManager);

        $editor = MockHelper::mock(\Lsp\Extension\DocumentManager\Editor\EditorInterface::class);
        // Position on "$value" parameter in add method (line 8, character 25)
        $context = new SelectionRangeContext(
            ProtocolFactory::textDocumentIdentifier(),
            [ProtocolFactory::position(8, 25)],
            $editor,
        );
        $consumer = new SelectionRangeConsumer();
        $contributor->contribute($context, $consumer);

        $this->assertArrayHasKey(0, $consumer->results);
        $result = $consumer->results[0];

        $ranges = $this->collectRangeChain($result);
        // Should have at minimum: variable -> expression/statement -> ...
        $this->assertGreaterThanOrEqual(2, count($ranges), 'Expected at least 2 nested ranges');
    }

    /**
     * @return list<SelectionRange> from innermost to outermost
     */
    private function collectRangeChain(SelectionRange $range): array
    {
        $chain = [$range];
        while ($range->parent !== null) {
            $range = $range->parent;
            $chain[] = $range;
        }

        return $chain;
    }

    private function assertRangeContains(SelectionRange $parent, SelectionRange $child, string $message = ''): void
    {
        $parentStart = $parent->range->start;
        $parentEnd = $parent->range->end;
        $childStart = $child->range->start;
        $childEnd = $child->range->end;

        $containsStart = $parentStart->line < $childStart->line
            || ($parentStart->line === $childStart->line && $parentStart->character <= $childStart->character);
        $containsEnd = $parentEnd->line > $childEnd->line
            || ($parentEnd->line === $childEnd->line && $parentEnd->character >= $childEnd->character);

        $this->assertTrue($containsStart && $containsEnd, $message ?: 'Parent range must contain child range');
    }
}

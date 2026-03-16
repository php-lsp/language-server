<?php

declare(strict_types=1);

namespace App\Tests\Unit\Module\FoldingRange;

use App\Core\Contracts\FoldingRange\FoldingRangeConsumer;
use App\Core\Contracts\FoldingRange\FoldingRangeContext;
use App\Module\FoldingRange\AstFoldingRangeContributor;
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
final class AstFoldingRangeContributorTest extends TestCase
{
    #[TestDox('returns empty when file not found')]
    public function testReturnsEmptyWhenNoFile(): void
    {
        $fileManager = MockHelper::mock(InMemoryPsiFileManager::class);
        $fileManager->method('findPsiFile')->willReturn(null);

        $contributor = new AstFoldingRangeContributor();
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

    #[TestDox('produces folding range for class body')]
    public function testClassFolding(): void
    {
        $code = <<<'PHP'
<?php
class Foo
{
    public function bar(): void {}
}
PHP;
        $results = $this->getFoldingRanges($code);

        $classRange = $this->findRange($results, 1, 4);
        $this->assertNotNull($classRange, 'Class should produce a folding range');
        $this->assertSame(FoldingRangeKind::Region, $classRange->kind);
    }

    #[TestDox('produces folding range for multi-line method')]
    public function testMethodFolding(): void
    {
        $code = <<<'PHP'
<?php
class Foo
{
    public function bar(): void
    {
        echo 'hello';
        echo 'world';
    }
}
PHP;
        $results = $this->getFoldingRanges($code);

        $methodRange = $this->findRange($results, 3, 7);
        $this->assertNotNull($methodRange, 'Method should produce a folding range');
    }

    #[TestDox('produces folding range for function')]
    public function testFunctionFolding(): void
    {
        $code = <<<'PHP'
<?php
function hello(): void
{
    echo 'hello';
    echo 'world';
}
PHP;
        $results = $this->getFoldingRanges($code);

        $funcRange = $this->findRange($results, 1, 5);
        $this->assertNotNull($funcRange, 'Function should produce a folding range');
    }

    #[TestDox('produces folding range for if/else blocks')]
    public function testIfElseFolding(): void
    {
        $code = <<<'PHP'
<?php
if (true) {
    echo 'yes';
    echo 'definitely';
} else {
    echo 'no';
    echo 'never';
}
PHP;
        $results = $this->getFoldingRanges($code);

        $ifRange = $this->findRange($results, 1, 7);
        $this->assertNotNull($ifRange, 'If block should produce a folding range');

        $elseRange = $this->findRange($results, 4, 7);
        $this->assertNotNull($elseRange, 'Else block should produce a folding range');
    }

    #[TestDox('produces folding range for multi-line array')]
    public function testArrayFolding(): void
    {
        $code = <<<'PHP'
<?php
$arr = [
    'a' => 1,
    'b' => 2,
    'c' => 3,
];
PHP;
        $results = $this->getFoldingRanges($code);

        $arrayRange = $this->findRange($results, 1, 5);
        $this->assertNotNull($arrayRange, 'Multi-line array should produce a folding range');
    }

    #[TestDox('produces folding range for switch/case')]
    public function testSwitchFolding(): void
    {
        $code = <<<'PHP'
<?php
switch ($x) {
    case 1:
        echo 'one';
        break;
    case 2:
        echo 'two';
        break;
}
PHP;
        $results = $this->getFoldingRanges($code);

        $switchRange = $this->findRange($results, 1, 8);
        $this->assertNotNull($switchRange, 'Switch should produce a folding range');
    }

    #[TestDox('produces folding range for try/catch')]
    public function testTryCatchFolding(): void
    {
        $code = <<<'PHP'
<?php
try {
    echo 'try';
    echo 'block';
} catch (\Exception $e) {
    echo 'catch';
    echo 'block';
}
PHP;
        $results = $this->getFoldingRanges($code);

        $tryRange = $this->findRange($results, 1, 7);
        $this->assertNotNull($tryRange, 'Try/catch should produce a folding range');
    }

    #[TestDox('produces folding range for interface')]
    public function testInterfaceFolding(): void
    {
        $code = <<<'PHP'
<?php
interface FooInterface
{
    public function bar(): void;
    public function baz(): void;
}
PHP;
        $results = $this->getFoldingRanges($code);

        $interfaceRange = $this->findRange($results, 1, 5);
        $this->assertNotNull($interfaceRange, 'Interface should produce a folding range');
    }

    #[TestDox('produces folding range for foreach loop')]
    public function testForeachFolding(): void
    {
        $code = <<<'PHP'
<?php
foreach ([1, 2, 3] as $item) {
    echo $item;
    echo "\n";
}
PHP;
        $results = $this->getFoldingRanges($code);

        $foreachRange = $this->findRange($results, 1, 4);
        $this->assertNotNull($foreachRange, 'Foreach should produce a folding range');
    }

    #[TestDox('skips single-line nodes')]
    public function testSkipsSingleLineNodes(): void
    {
        $code = '<?php class Foo {}';
        $results = $this->getFoldingRanges($code);

        $this->assertEmpty($results, 'Single-line class should not produce a folding range');
    }

    /**
     * @return list<FoldingRange>
     */
    private function getFoldingRanges(string $code): array
    {
        $psiFile = PsiFileFactory::fromCode($code);
        $fileManager = MockHelper::mock(InMemoryPsiFileManager::class);
        $fileManager->method('findPsiFile')->willReturn($psiFile);

        $contributor = new AstFoldingRangeContributor();
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

    private function findRange(array $ranges, int $startLine, int $endLine): ?FoldingRange
    {
        foreach ($ranges as $range) {
            if ($range->startLine === $startLine && $range->endLine === $endLine) {
                return $range;
            }
        }

        return null;
    }
}

<?php

declare(strict_types=1);

namespace App\Tests\Unit\Module\Completion;

use App\Module\Completion\ConstantCompletionContributor;
use App\Module\Indexing\Storage\IndexData\ConstantData;
use App\Tests\Support\CompletionTestHelper;
use App\Tests\Support\IndexTestHelper;
use App\Tests\Support\PsiFileFactory;
use App\Tests\Support\ProtocolFactory;
use App\Tests\TestCase;
use Lsp\Protocol\Type\CompletionItemKind;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\TestDox;

#[Group('unit')]
final class ConstantCompletionContributorTest extends TestCase
{
    #[TestDox('completes constant names from index')]
    public function testCompletesConstantNames(): void
    {
        $lookup = IndexTestHelper::createLookup([
            'php.constants.fqn' => [
                'file:///test.php' => ['MY_CONST' => new ConstantData('MY_CONST', null, 0, 10, null, '42')],
            ],
        ]);
        $contributor = new ConstantCompletionContributor($lookup);
        $psiFile = PsiFileFactory::fromCode('<?php echo MY_C');
        $results = CompletionTestHelper::contribute(
            $contributor,
            $psiFile,
            ProtocolFactory::position(0, 14),
        );

        $this->assertNotEmpty($results);
        $this->assertSame(CompletionItemKind::ConstantKind, $results[0]->kind);
        $this->assertSame('MY_CONST', $results[0]->label);
    }

    #[TestDox('returns empty when no match')]
    public function testReturnsEmptyWhenNoMatch(): void
    {
        $lookup = IndexTestHelper::createLookup([
            'php.constants.fqn' => [
                'file:///test.php' => ['MY_CONST' => new ConstantData('MY_CONST', null, 0, 10, null, '42')],
            ],
        ]);
        $contributor = new ConstantCompletionContributor($lookup);
        $psiFile = PsiFileFactory::fromCode('<?php echo ZZZZZ');
        $results = CompletionTestHelper::contribute(
            $contributor,
            $psiFile,
            ProtocolFactory::position(0, 16),
        );

        $this->assertEmpty($results);
    }
}

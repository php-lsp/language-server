<?php

declare(strict_types=1);

namespace App\Tests\Unit\Module\Completion;

use App\Module\Completion\EnumCaseCompletionContributor;
use App\Module\Indexing\Data\ConstantData;
use App\Module\Indexing\Data\EnumData;
use App\Tests\Support\CompletionTestHelper;
use App\Tests\Support\IndexTestHelper;
use App\Tests\Support\PsiFileFactory;
use App\Tests\Support\ProtocolFactory;
use App\Tests\TestCase;
use Lsp\Protocol\Type\CompletionItemKind;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\TestDox;

#[Group('unit')]
final class EnumCaseCompletionContributorTest extends TestCase
{
    #[TestDox('completes enum case names from index')]
    public function testCompletesEnumCaseNames(): void
    {
        $lookup = IndexTestHelper::createLookup([
            'php.enums.fqn' => [
                'file:///test.php' => ['Suit' => new EnumData('Suit', 0, 10, 'string', [])],
            ],
            'php.classConstants.fqn' => [
                'file:///test.php' => ['Suit::Hearts' => new ConstantData('Hearts', 'Suit', 0, 10, null, null)],
            ],
        ]);
        $contributor = new EnumCaseCompletionContributor($lookup);
        $psiFile = PsiFileFactory::fromCode('<?php Suit::H;');
        $results = CompletionTestHelper::contribute(
            $contributor,
            $psiFile,
            ProtocolFactory::position(0, 13),
        );

        $this->assertNotEmpty($results);
        $this->assertSame(CompletionItemKind::EnumMemberKind, $results[0]->kind);
        $this->assertSame('Hearts', $results[0]->label);
    }

    #[TestDox('returns empty when class is not an enum')]
    public function testReturnsEmptyWhenNoMatch(): void
    {
        $lookup = IndexTestHelper::createLookup([
            'php.enums.fqn' => [
                'file:///test.php' => ['Suit' => new EnumData('Suit', 0, 10, 'string', [])],
            ],
            'php.classConstants.fqn' => [
                'file:///test.php' => ['Suit::Hearts' => new ConstantData('Hearts', 'Suit', 0, 10, null, null)],
            ],
        ]);
        $contributor = new EnumCaseCompletionContributor($lookup);
        $psiFile = PsiFileFactory::fromCode('<?php Other::H;');
        $results = CompletionTestHelper::contribute(
            $contributor,
            $psiFile,
            ProtocolFactory::position(0, 14),
        );

        $this->assertEmpty($results);
    }
}

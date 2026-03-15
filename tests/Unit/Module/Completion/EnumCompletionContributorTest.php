<?php

declare(strict_types=1);

namespace App\Tests\Unit\Module\Completion;

use App\Module\Completion\EnumCompletionContributor;
use App\Module\Indexing\Storage\IndexData\EnumData;
use App\Tests\Support\CompletionTestHelper;
use App\Tests\Support\IndexTestHelper;
use App\Tests\Support\PsiFileFactory;
use App\Tests\Support\ProtocolFactory;
use App\Tests\TestCase;
use Lsp\Protocol\Type\CompletionItemKind;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\TestDox;

#[Group('unit')]
final class EnumCompletionContributorTest extends TestCase
{
    #[TestDox('completes enum names from index')]
    public function testCompletesEnumNames(): void
    {
        $lookup = IndexTestHelper::createLookup([
            'php.enums.fqn' => [
                'file:///test.php' => ['App\\MyEnum' => new EnumData('App\\MyEnum', 0, 10, null, [])],
            ],
        ]);
        $contributor = new EnumCompletionContributor($lookup);
        $psiFile = PsiFileFactory::fromCode('<?php new MyEn');
        $results = CompletionTestHelper::contribute(
            $contributor,
            $psiFile,
            ProtocolFactory::position(0, 13),
        );

        $this->assertNotEmpty($results);
        $this->assertSame(CompletionItemKind::EnumKind, $results[0]->kind);
        $this->assertSame('App\\MyEnum', $results[0]->label);
    }

    #[TestDox('returns empty when no match')]
    public function testReturnsEmptyWhenNoMatch(): void
    {
        $lookup = IndexTestHelper::createLookup([
            'php.enums.fqn' => [
                'file:///test.php' => ['App\\MyEnum' => new EnumData('App\\MyEnum', 0, 10, null, [])],
            ],
        ]);
        $contributor = new EnumCompletionContributor($lookup);
        $psiFile = PsiFileFactory::fromCode('<?php new Zzzzz');
        $results = CompletionTestHelper::contribute(
            $contributor,
            $psiFile,
            ProtocolFactory::position(0, 14),
        );

        $this->assertEmpty($results);
    }
}

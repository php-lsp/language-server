<?php

declare(strict_types=1);

namespace App\Tests\Unit\Module\Completion;

use App\Module\Completion\TraitCompletionContributor;
use App\Module\Indexing\Storage\IndexData\TraitData;
use App\Tests\Support\CompletionTestHelper;
use App\Tests\Support\IndexTestHelper;
use App\Tests\Support\PsiFileFactory;
use App\Tests\Support\ProtocolFactory;
use App\Tests\TestCase;
use Lsp\Protocol\Type\CompletionItemKind;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\TestDox;

#[Group('unit')]
final class TraitCompletionContributorTest extends TestCase
{
    #[TestDox('completes trait names from index')]
    public function testCompletesTraitNames(): void
    {
        $lookup = IndexTestHelper::createLookup([
            'php.traits.fqn' => [
                'file:///test.php' => ['App\\MyTrait' => new TraitData('App\\MyTrait', 0, 10)],
            ],
        ]);
        $contributor = new TraitCompletionContributor($lookup);
        $psiFile = PsiFileFactory::fromCode('<?php use MyTr');
        $results = CompletionTestHelper::contribute(
            $contributor,
            $psiFile,
            ProtocolFactory::position(0, 13),
        );

        $this->assertNotEmpty($results);
        $this->assertSame(CompletionItemKind::ClassKind, $results[0]->kind);
        $this->assertSame('App\\MyTrait', $results[0]->label);
    }

    #[TestDox('returns empty when no match')]
    public function testReturnsEmptyWhenNoMatch(): void
    {
        $lookup = IndexTestHelper::createLookup([
            'php.traits.fqn' => [
                'file:///test.php' => ['App\\MyTrait' => new TraitData('App\\MyTrait', 0, 10)],
            ],
        ]);
        $contributor = new TraitCompletionContributor($lookup);
        $psiFile = PsiFileFactory::fromCode('<?php use Zzzzz');
        $results = CompletionTestHelper::contribute(
            $contributor,
            $psiFile,
            ProtocolFactory::position(0, 14),
        );

        $this->assertEmpty($results);
    }
}

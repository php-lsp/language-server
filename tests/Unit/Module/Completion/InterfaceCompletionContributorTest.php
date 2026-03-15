<?php

declare(strict_types=1);

namespace App\Tests\Unit\Module\Completion;

use App\Module\Completion\InterfaceCompletionContributor;
use App\Module\Indexing\Storage\IndexData\InterfaceData;
use App\Tests\Support\CompletionTestHelper;
use App\Tests\Support\IndexTestHelper;
use App\Tests\Support\PsiFileFactory;
use App\Tests\Support\ProtocolFactory;
use App\Tests\TestCase;
use Lsp\Protocol\Type\CompletionItemKind;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\TestDox;

#[Group('unit')]
final class InterfaceCompletionContributorTest extends TestCase
{
    #[TestDox('completes interface names from index')]
    public function testCompletesInterfaceNames(): void
    {
        $lookup = IndexTestHelper::createLookup([
            'php.interfaces.fqn' => [
                'file:///test.php' => ['App\\MyInterface' => new InterfaceData('App\\MyInterface', 0, 10, [])],
            ],
        ]);
        $contributor = new InterfaceCompletionContributor($lookup);
        $psiFile = PsiFileFactory::fromCode('<?php new MyIn');
        $results = CompletionTestHelper::contribute(
            $contributor,
            $psiFile,
            ProtocolFactory::position(0, 13),
        );

        $this->assertNotEmpty($results);
        $this->assertSame(CompletionItemKind::InterfaceKind, $results[0]->kind);
        $this->assertSame('App\\MyInterface', $results[0]->label);
    }

    #[TestDox('returns empty when no match')]
    public function testReturnsEmptyWhenNoMatch(): void
    {
        $lookup = IndexTestHelper::createLookup([
            'php.interfaces.fqn' => [
                'file:///test.php' => ['App\\MyInterface' => new InterfaceData('App\\MyInterface', 0, 10, [])],
            ],
        ]);
        $contributor = new InterfaceCompletionContributor($lookup);
        $psiFile = PsiFileFactory::fromCode('<?php new Zzzzz');
        $results = CompletionTestHelper::contribute(
            $contributor,
            $psiFile,
            ProtocolFactory::position(0, 14),
        );

        $this->assertEmpty($results);
    }
}

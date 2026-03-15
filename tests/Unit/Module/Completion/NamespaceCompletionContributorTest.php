<?php

declare(strict_types=1);

namespace App\Tests\Unit\Module\Completion;

use App\Module\Completion\NamespaceCompletionContributor;
use App\Module\Indexing\Storage\IndexData\NamespaceData;
use App\Tests\Support\CompletionTestHelper;
use App\Tests\Support\IndexTestHelper;
use App\Tests\Support\PsiFileFactory;
use App\Tests\Support\ProtocolFactory;
use App\Tests\TestCase;
use Lsp\Protocol\Type\CompletionItemKind;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\TestDox;

#[Group('unit')]
final class NamespaceCompletionContributorTest extends TestCase
{
    #[TestDox('completes namespace names inside namespace declaration')]
    public function testCompletesNamespaceNames(): void
    {
        $lookup = IndexTestHelper::createLookup([
            'php.namespaces.fqn' => [
                'file:///test.php' => ['App\\Models' => new NamespaceData('App\\Models', 0, 20)],
            ],
        ]);
        $contributor = new NamespaceCompletionContributor($lookup);
        $psiFile = PsiFileFactory::fromCode('<?php namespace Ap;');
        $results = CompletionTestHelper::contribute(
            $contributor,
            $psiFile,
            ProtocolFactory::position(0, 17),
        );

        $this->assertNotEmpty($results);
        $this->assertSame(CompletionItemKind::ModuleKind, $results[0]->kind);
        $this->assertSame('App\\Models', $results[0]->label);
    }

    #[TestDox('returns empty when not in namespace declaration')]
    public function testReturnsEmptyWhenNoMatch(): void
    {
        $lookup = IndexTestHelper::createLookup([
            'php.namespaces.fqn' => [
                'file:///test.php' => ['App\\Models' => new NamespaceData('App\\Models', 0, 20)],
            ],
        ]);
        $contributor = new NamespaceCompletionContributor($lookup);
        $psiFile = PsiFileFactory::fromCode('<?php echo 1;');
        $results = CompletionTestHelper::contribute(
            $contributor,
            $psiFile,
            ProtocolFactory::position(0, 5),
        );

        $this->assertEmpty($results);
    }
}

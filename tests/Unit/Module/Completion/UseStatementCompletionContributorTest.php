<?php

declare(strict_types=1);

namespace App\Tests\Unit\Module\Completion;

use App\Module\Completion\UseStatementCompletionContributor;
use App\Module\Indexing\Storage\IndexData\ClassData;
use App\Tests\Support\CompletionTestHelper;
use App\Tests\Support\IndexTestHelper;
use App\Tests\Support\PsiFileFactory;
use App\Tests\Support\ProtocolFactory;
use App\Tests\TestCase;
use Lsp\Protocol\Type\CompletionItemKind;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\TestDox;

#[Group('unit')]
final class UseStatementCompletionContributorTest extends TestCase
{
    #[TestDox('completes class names inside use statement')]
    public function testCompletesClassNamesInUseStatement(): void
    {
        $lookup = IndexTestHelper::createLookup([
            'php.classes.fqn' => [
                'file:///test.php' => ['App\\MyClass' => new ClassData('App\\MyClass', 0, 10, null, [], false, false)],
            ],
        ]);
        $contributor = new UseStatementCompletionContributor($lookup);
        $psiFile = PsiFileFactory::fromCode('<?php use App\\My;');
        $results = CompletionTestHelper::contribute(
            $contributor,
            $psiFile,
            ProtocolFactory::position(0, 15),
        );

        $this->assertNotEmpty($results);
        $this->assertSame(CompletionItemKind::ClassKind, $results[0]->kind);
        $this->assertSame('App\\MyClass', $results[0]->label);
    }

    #[TestDox('returns empty when not in use statement')]
    public function testReturnsEmptyWhenNoMatch(): void
    {
        $lookup = IndexTestHelper::createLookup([
            'php.classes.fqn' => [
                'file:///test.php' => ['App\\MyClass' => new ClassData('App\\MyClass', 0, 10, null, [], false, false)],
            ],
        ]);
        $contributor = new UseStatementCompletionContributor($lookup);
        $psiFile = PsiFileFactory::fromCode('<?php echo 1;');
        $results = CompletionTestHelper::contribute(
            $contributor,
            $psiFile,
            ProtocolFactory::position(0, 5),
        );

        $this->assertEmpty($results);
    }
}

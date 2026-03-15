<?php

declare(strict_types=1);

namespace App\Tests\Unit\Module\Completion;

use App\Module\Completion\ClassesCompletionContributor;
use App\Module\Indexing\Data\ClassData;
use App\Module\Indexing\Indexer\ClassIndexer;
use App\Tests\Support\CompletionTestHelper;
use App\Tests\Support\IndexTestHelper;
use App\Tests\Support\PsiFileFactory;
use App\Tests\Support\ProtocolFactory;
use App\Tests\TestCase;
use Lsp\Protocol\Type\CompletionItemKind;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\TestDox;

#[Group('unit')]
final class ClassesCompletionContributorTest extends TestCase
{
    #[TestDox('completes class names from index')]
    public function testCompletesClassNames(): void
    {
        $lookup = IndexTestHelper::createLookup([
            'php.classes.fqn' => [
                'file:///test.php' => ['App\\MyClass' => new ClassData('App\\MyClass', 0, 50, false, false, false, null, [])],
            ],
        ]);
        $contributor = new ClassesCompletionContributor($lookup);
        $psiFile = PsiFileFactory::fromCode('<?php new MyCl');
        $results = CompletionTestHelper::contribute(
            $contributor,
            $psiFile,
            ProtocolFactory::position(0, 13),
        );

        $this->assertNotEmpty($results);
        $this->assertSame(CompletionItemKind::ClassKind, $results[0]->kind);
        $this->assertSame('App\\MyClass', $results[0]->label);
    }

    #[TestDox('returns empty when no match')]
    public function testReturnsEmptyWhenNoMatch(): void
    {
        $lookup = IndexTestHelper::createLookup([
            'php.classes.fqn' => [
                'file:///test.php' => ['App\\Foo' => new ClassData('App\\Foo', 0, 50, false, false, false, null, [])],
            ],
        ]);
        $contributor = new ClassesCompletionContributor($lookup);
        $psiFile = PsiFileFactory::fromCode('<?php new Zzzzz');
        $results = CompletionTestHelper::contribute(
            $contributor,
            $psiFile,
            ProtocolFactory::position(0, 14),
        );

        $this->assertEmpty($results);
    }
}

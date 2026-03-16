<?php

declare(strict_types=1);

namespace App\Tests\Unit\Module\Completion;

use App\Module\Completion\ClassConstantCompletionContributor;
use App\Module\Indexing\Data\ConstantData;
use App\Tests\Support\CompletionTestHelper;
use App\Tests\Support\IndexTestHelper;
use App\Tests\Support\PsiFileFactory;
use App\Tests\Support\ProtocolFactory;
use App\Tests\TestCase;
use Lsp\Protocol\Type\CompletionItemKind;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\TestDox;

#[Group('unit')]
final class ClassConstantCompletionContributorTest extends TestCase
{
    #[TestDox('completes class constant names from index')]
    public function testCompletesClassConstantNames(): void
    {
        $lookup = IndexTestHelper::createLookup([
            'php.classConstants.fqn' => [
                'file:///test.php' => ['Foo::BAR' => new ConstantData('BAR', 'Foo', 0, 10, null, null)],
            ],
        ]);
        $contributor = new ClassConstantCompletionContributor($lookup);
        $psiFile = PsiFileFactory::fromCode('<?php Foo::B;');
        $results = CompletionTestHelper::contribute(
            $contributor,
            $psiFile,
            ProtocolFactory::position(0, 12),
        );

        $this->assertNotEmpty($results);
        $this->assertSame(CompletionItemKind::ConstantKind, $results[0]->kind);
        $this->assertSame('BAR', $results[0]->label);
    }

    #[TestDox('returns empty when class name does not match')]
    public function testReturnsEmptyWhenNoMatch(): void
    {
        $lookup = IndexTestHelper::createLookup([
            'php.classConstants.fqn' => [
                'file:///test.php' => ['Foo::BAR' => new ConstantData('BAR', 'Foo', 0, 10, null, null)],
            ],
        ]);
        $contributor = new ClassConstantCompletionContributor($lookup);
        $psiFile = PsiFileFactory::fromCode('<?php Bar::B;');
        $results = CompletionTestHelper::contribute(
            $contributor,
            $psiFile,
            ProtocolFactory::position(0, 12),
        );

        $this->assertEmpty($results);
    }
}
